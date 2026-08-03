<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
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
 * Assembles the Monday „Wochenüberblick" for one parent: the Hort-wide week program
 * (food + activities + homework) plus a per-child summary of what's planned for their
 * own child(ren) this week (pickup per day, absences, excursions they're on). Modeled
 * on {@see CompanionNotes::for()} and the assembly in WeeklyOverviewController.
 */
class WeeklyDigestBuilder
{
    /**
     * @return array{
     *   week_label: string,
     *   program: array<int, array{weekday: string, lunch: ?string, activity: ?string, homework: ?string}>,
     *   excursions: array<int, array{name: string, day: string}>,
     *   children: array<int, array{name: string, days: array<int, array{weekday: string, summary: string}>}>,
     * }
     */
    public static function for(User $parent, Carbon $weekStart): array
    {
        $monday = $weekStart->copy()->startOfDay();
        $weekDays = collect(range(0, 4))->map(fn (int $i) => $monday->copy()->addDays($i));
        $weekDates = $weekDays->map(fn (Carbon $d) => $d->toDateString())->all();
        $friday = $monday->copy()->addDays(4);

        $locale = $parent->preferredLocale() ?? app()->getLocale();
        $weekdayLabel = fn (Carbon $d): string => $d->copy()->locale($locale)->isoFormat('dddd');

        // Schließzeiten replace a day's whole content: no food, no activity, no pickup.
        $closedDays = HolidayPeriod::closedDaysBetween($monday, $friday);
        // Ferienbetreuung days keep food and activity but swap the Stammplan for the
        // sign-ups — a child without one simply isn't there that day.
        $careDays = HolidayCareDay::betweenKeyed($monday, $friday);

        // Hort-wide program per weekday (lunch + activity + effective homework).
        $programs = DailyProgram::query()
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyProgram $p) => $p->date->toDateString());
        $homeworkDefaults = HomeworkDefault::all()->keyBy('weekday');

        $program = $weekDays->map(function (Carbon $day, int $i) use ($programs, $homeworkDefaults, $weekdayLabel, $closedDays, $careDays) {
            if (isset($closedDays[$day->toDateString()])) {
                return [
                    'weekday' => $weekdayLabel($day),
                    'lunch' => null,
                    'activity' => null,
                    'homework' => null,
                    'care' => null,
                    'closed' => $closedDays[$day->toDateString()],
                ];
            }

            $p = $programs->get($day->toDateString());
            $care = $careDays->get($day->toDateString());

            // No school on a care day, so the per-weekday homework default doesn't
            // apply — but the Betreuungszeit does, and parents need to see it.
            [$hwStart, $hwEnd] = $care
                ? [null, null]
                : DailyProgram::effectiveHomework($p, $homeworkDefaults->get($i + 1));

            $homework = null;
            if ($hwStart) {
                $homework = self::short($hwStart).($hwEnd ? '–'.self::short($hwEnd) : '');
            }

            return [
                'weekday' => $weekdayLabel($day),
                'lunch' => $p?->lunch,
                'activity' => $p?->activity,
                'homework' => $homework,
                'care' => $care ? $care->window() : null,
                'closed' => null,
            ];
        })->all();

        // This week's excursions (Hort-wide list).
        $excursions = Excursion::query()
            ->whereIn('date', $weekDates)
            ->orderBy('date')
            ->orderBy('depart_at')
            ->get()
            ->map(fn (Excursion $e) => [
                'name' => $e->name,
                'day' => $weekdayLabel($e->date),
            ])
            ->all();

        // Per-child summary — this parent's own children enrolled during the week.
        $children = $parent->children()
            ->activeBetween($weekDays->first(), $weekDays->last())
            ->orderBy('name')->get();
        $childIds = $children->pluck('id')->all();

        $plans = EffectivePlan::forMany($childIds, $weekDates);

        $absences = Absence::query()
            ->whereIn('child_id', $childIds)
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (Absence $a) => $a->child_id.'|'.$a->date->toDateString());

        // Which trips each of this parent's children actually joins, per date.
        $excursionByChildDate = [];
        Excursion::query()
            ->with('participants:id')
            ->whereIn('date', $weekDates)
            ->get()
            ->each(function (Excursion $e) use (&$excursionByChildDate, $childIds): void {
                foreach ($e->participants as $participant) {
                    if (in_array($participant->id, $childIds, true)) {
                        $excursionByChildDate[$participant->id.'|'.$e->date->toDateString()] = $e->name;
                    }
                }
            });

        $childNames = Child::query()->whereKey(
            collect($plans)->pluck('companion_child_id')->filter()->all()
        )->pluck('name', 'id');

        // Sign-ups for the week's Ferienbetreuung days: on those dates a DailyDeparture
        // *is* the registration, so its absence means the child isn't coming.
        $signedUp = $careDays->isEmpty()
            ? collect()
            : DailyDeparture::query()
                // Keyed off the care day: an override that happens to fall on an
                // offered date is not a sign-up.
                ->whereIn('holiday_care_day_id', $careDays->pluck('id'))
                ->whereIn('child_id', $childIds)
                ->get()
                ->map(fn (DailyDeparture $d): string => $d->child_id.'|'.$d->date->toDateString())
                ->flip();

        $childSummaries = $children->map(function (Child $child) use ($weekDays, $plans, $absences, $excursionByChildDate, $childNames, $weekdayLabel, $closedDays, $careDays, $signedUp) {
            $days = $weekDays->map(function (Carbon $day) use ($child, $plans, $absences, $excursionByChildDate, $childNames, $weekdayLabel, $closedDays, $careDays, $signedUp) {
                $date = $day->toDateString();

                // Closed for everyone — the child's own plan doesn't apply.
                if (isset($closedDays[$date])) {
                    return ['weekday' => $weekdayLabel($day), 'summary' => "🚫 {$closedDays[$date]}"];
                }

                // Ferienbetreuung: without a sign-up the child isn't there, and their
                // Stammplan must not be reported as if they were.
                if ($careDays->has($date) && ! $signedUp->has($child->id.'|'.$date)) {
                    return ['weekday' => $weekdayLabel($day), 'summary' => 'nicht angemeldet'];
                }

                $absence = $absences->get($child->id.'|'.$date);
                if ($absence) {
                    return ['weekday' => $weekdayLabel($day), 'summary' => $absence->reason->label()];
                }

                $plan = $plans[$child->id.'|'.$date] ?? null;
                $summary = self::planSummary($plan, $date, $childNames);

                if (isset($excursionByChildDate[$child->id.'|'.$date])) {
                    $trip = $excursionByChildDate[$child->id.'|'.$date];
                    $summary = "🚌 {$trip}".($summary ? " · {$summary}" : '');
                }

                return ['weekday' => $weekdayLabel($day), 'summary' => $summary ?: '–'];
            })->all();

            return ['name' => $child->name, 'days' => $days];
        })->all();

        $weekLabel = $monday->copy()->locale($locale)->isoFormat('D.M.').'–'.$friday->copy()->locale($locale)->isoFormat('D.M.YYYY');

        return [
            'week_label' => $weekLabel,
            'program' => $program,
            'excursions' => $excursions,
            'children' => $childSummaries,
        ];
    }

    /**
     * A one-line pickup description for a resolved effective plan.
     *
     * @param  array{time: ?string, method: ?string, qualifier: ?string, companion_child_id: ?int}|null  $plan
     * @param  Collection<int, string>  $childNames
     */
    private static function planSummary(?array $plan, string $date, $childNames): string
    {
        if ($plan === null || $plan['method'] === null) {
            return '';
        }

        // „geht mit … mit": mirror the companion (time is resolved one level up),
        // including their bis/ab qualifier.
        if ($plan['method'] === DepartureMethod::WithChild->value && $plan['companion_child_id']) {
            $companion = $childNames[$plan['companion_child_id']] ?? '';
            $mirror = EffectivePlan::for($plan['companion_child_id'], $date);
            $prefix = $mirror['qualifier'] && $mirror['qualifier'] !== TimeQualifier::At->value
                ? TimeQualifier::from($mirror['qualifier'])->prefix().' '
                : '';

            return trim("geht mit {$companion} mit".($mirror['time'] ? " ({$prefix}{$mirror['time']})" : ''));
        }

        $time = $plan['time'];
        $method = DepartureMethod::from($plan['method'])->label();

        if ($plan['method'] === DepartureMethod::SentHome->value && $time) {
            $prefix = $plan['qualifier'] && $plan['qualifier'] !== TimeQualifier::At->value
                ? TimeQualifier::from($plan['qualifier'])->prefix().' '
                : '';

            return "{$method} {$prefix}{$time}";
        }

        return trim($method.($time ? " {$time}" : ''));
    }

    private static function short(?string $time): ?string
    {
        return $time ? substr((string) $time, 0, 5) : null;
    }
}
