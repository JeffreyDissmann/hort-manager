<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\HomeworkDefault;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every pickup of this parent's children that lands in the middle of something: the
 * Hausaufgabenzeit, a timed Aktivität, or an Ausflug their child joins. The board and
 * the Wochenplan flag these per day — this is the standing summary, so a family sees
 * the whole picture without walking the week.
 *
 * On a Ferienbetreuung day the test is inverted: there the pickup has to sit *inside*
 * the Betreuungszeit, because that is when the Hort is staffed.
 *
 * Two kinds of finding:
 *  - **recurring** — the Stammplan itself collides with the weekday's default
 *    Hausaufgabenzeit, so it happens every week until the Stammplan changes;
 *  - **dated** — a specific day, because the day's plan, its program or an Ausflug
 *    makes it so. A dated hit that is merely this week's instance of a recurring one
 *    is left out: saying the same thing twice makes both easier to ignore.
 */
class PickupClashes
{
    /** How far ahead to look. Two weeks is „the plan you can still change". */
    private const DAYS_AHEAD = 14;

    /**
     * @return array{recurring: list<array<string, mixed>>, dated: list<array<string, mixed>>}
     */
    public static function for(?User $user): array
    {
        if (! $user || $user->isStaff()) {
            return ['recurring' => [], 'dated' => []];
        }

        $today = Carbon::today();
        $until = $today->copy()->addDays(self::DAYS_AHEAD);

        $children = $user->children()
            ->activeBetween($today, $until)
            ->with('weeklySchedules')
            ->orderBy('name')
            ->get();

        if ($children->isEmpty()) {
            return ['recurring' => [], 'dated' => []];
        }

        $defaults = HomeworkDefault::all()->keyBy('weekday');

        return [
            'recurring' => self::recurring($children, $defaults),
            'dated' => self::dated($children, $defaults, $today, $until),
        ];
    }

    /**
     * The Stammplan against the weekday's default Hausaufgabenzeit — a collision that
     * repeats every week, so it is reported per weekday rather than per date.
     *
     * @param  Collection<int, Child>  $children
     * @param  Collection<int, HomeworkDefault>  $defaults
     * @return list<array<string, mixed>>
     */
    private static function recurring($children, $defaults): array
    {
        $clashes = [];

        foreach ($children as $child) {
            foreach ($child->weeklySchedules as $schedule) {
                $default = $defaults->get($schedule->weekday);
                $time = self::short($schedule->planned_time);

                if (! $time || ! $default?->start_time || ! $default->end_time) {
                    continue;
                }

                $from = self::short($default->start_time);
                $to = self::short($default->end_time);

                if (DayWindows::contains($time, $from, $to)) {
                    $clashes[] = [
                        'child' => $child->name,
                        'child_id' => $child->id,
                        'weekday' => $schedule->weekday,
                        'time' => $time,
                        'kind' => 'homework',
                        'name' => null,
                        'from' => $from,
                        'to' => $to,
                    ];
                }
            }
        }

        return $clashes;
    }

    /**
     * Upcoming days whose pickup lands inside the day's Hausaufgaben, a timed Aktivität
     * or an Ausflug the child is on.
     *
     * @param  Collection<int, Child>  $children
     * @param  Collection<int, HomeworkDefault>  $defaults
     * @return list<array<string, mixed>>
     */
    private static function dated($children, $defaults, Carbon $today, Carbon $until): array
    {
        $dates = [];
        for ($day = $today->copy(); $day->lessThanOrEqualTo($until); $day->addDay()) {
            if ($day->isWeekday()) {
                $dates[] = $day->toDateString();
            }
        }

        $childIds = $children->pluck('id')->all();
        $closedDays = HolidayPeriod::closedDaysBetween($today, $until);
        $careDays = HolidayCareDay::betweenKeyed($today, $until);
        $plans = EffectivePlan::forMany($childIds, $dates);

        // „Geht mit … mit" mirrors the companion's time, so those children's plans are
        // needed too — in one batch. Resolving them per row ran an EffectivePlan::for
        // (two queries) per companion day, on a prop shared with every request.
        $companionIds = array_values(array_unique(array_filter(array_map(
            fn (array $plan): ?int => $plan['method'] === 'with_child' ? $plan['companion_child_id'] : null,
            $plans,
        ))));
        $companionPlans = EffectivePlan::forMany($companionIds, $dates);

        $programs = DailyProgram::whereIn('date', $dates)->get()
            ->keyBy(fn (DailyProgram $p): string => $p->date->toDateString());

        $absences = Absence::whereIn('child_id', $childIds)->whereIn('date', $dates)->get()
            ->map(fn (Absence $a): string => $a->child_id.'|'.$a->date->toDateString())
            ->flip();

        // Days that carry a plan of their own — those can say something the recurring
        // Stammplan finding doesn't.
        $overrides = DailyDeparture::whereIn('child_id', $childIds)->whereIn('date', $dates)->get()
            ->map(fn (DailyDeparture $d): string => $d->child_id.'|'.$d->date->toDateString())
            ->flip();

        // Trips each child actually joins, per date.
        $trips = [];
        foreach (Excursion::with('participants:id')->whereIn('date', $dates)->get() as $excursion) {
            foreach ($excursion->participants as $participant) {
                $trips[$participant->id.'|'.$excursion->date->toDateString()] = $excursion;
            }
        }

        $clashes = [];

        foreach ($dates as $date) {
            if (isset($closedDays[$date])) {
                continue;
            }

            $windows = DayWindows::program(
                $programs->get($date),
                $defaults->get(Carbon::parse($date)->dayOfWeekIso),
                $careDays->has($date),
            );

            foreach ($children as $child) {
                $key = $child->id.'|'.$date;

                if ($absences->has($key)) {
                    continue;
                }

                $plan = $plans[$key] ?? null;
                $time = self::effectiveTime($plan, $date, $companionPlans);

                if ($time === null) {
                    continue;
                }

                // The day's own plan, or the Stammplan showing through? A row alone
                // doesn't settle it: the board seeds one from the Stammplan the first
                // time it is opened, and that row says nothing new. Only a time that
                // actually differs makes this day worth its own line.
                $standard = self::short($child->weeklySchedules
                    ->firstWhere('weekday', Carbon::parse($date)->dayOfWeekIso)?->planned_time);
                $isOverride = $overrides->has($key) && $time !== $standard;

                $found = DayWindows::hits($time, [
                    ...$windows,
                    ...array_filter([DayWindows::excursion($trips[$key] ?? null)]),
                ]);

                foreach ($found as $window) {
                    // This week's instance of the recurring Stammplan finding.
                    if ($window['kind'] === 'homework' && $window['from_default'] && ! $isOverride) {
                        continue;
                    }

                    $clashes[] = [...$window, 'child' => $child->name, 'child_id' => $child->id, 'date' => $date, 'time' => $time];
                }

                // Ferienbetreuung is the one window a pickup has to sit *inside*: the
                // Hort is only staffed between starts_at and ends_at, so a 17:00 pickup
                // on a day that ends at 16:00 means nobody is there to hand the child
                // over. The end of the window itself is the normal case, so it counts as
                // inside — hence the explicit `> to` rather than a contains() call.
                $care = DayWindows::care($careDays->get($date));

                if ($care !== null && ($time < $care['from'] || $time > $care['to'])) {
                    $clashes[] = [...$care, 'child' => $child->name, 'child_id' => $child->id, 'date' => $date, 'time' => $time];
                }
            }
        }

        usort($clashes, fn (array $a, array $b) => [$a['date'], $a['child']] <=> [$b['date'], $b['child']]);

        return $clashes;
    }

    /**
     * The pickup a family would read for that day — „geht mit … mit" mirrors the
     * companion's time, exactly as the board and the Wochenplan show it.
     *
     * @param  array<string, mixed>|null  $plan
     * @param  array<string, array<string, mixed>>  $companionPlans  keyed „{childId}|{date}"
     */
    private static function effectiveTime(?array $plan, string $date, array $companionPlans): ?string
    {
        if ($plan === null) {
            return null;
        }

        if ($plan['method'] === 'with_child' && $plan['companion_child_id']) {
            return $companionPlans[$plan['companion_child_id'].'|'.$date]['time'] ?? null;
        }

        return $plan['time'];
    }

    private static function short(mixed $time): ?string
    {
        return $time ? substr((string) $time, 0, 5) : null;
    }
}
