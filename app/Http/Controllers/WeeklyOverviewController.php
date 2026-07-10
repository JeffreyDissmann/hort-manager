<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\TimeQualifier;
use App\Http\Controllers\Concerns\ResolvesWeek;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
use App\Support\CompanionNotes;
use App\Support\EffectivePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyOverviewController extends Controller
{
    use ResolvesWeek;

    /**
     * The Wochenplan: a navigable week's effective plan (Stammplan + same-day
     * overrides). Staff and a child's own parents may adjust individual days.
     * The read-only Stammplan itself lives on its own page (StandardPlanController).
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();

        [$week, $weekDays] = $this->resolveWeek($request);

        // "Diese Woche" is the user's editable view: a parent sees only their own
        // children, staff see all. The standard timetable below always shows everyone.
        $weekChildren = $user->isStaff()
            ? Child::query()->with('weeklySchedules')->orderBy('name')->get(['id', 'name', 'date_of_birth'])
            : $user->children()->with('weeklySchedules')->orderBy('name')->get();

        // All children — for the companion picker and name lookups (a companion may be
        // any child, not just one the current user manages). The picker excludes the
        // child currently being edited client-side (which one is dynamic per edit), and
        // the adjust endpoint enforces it with a `different:child_id` rule.
        $allChildren = Child::query()->with('weeklySchedules')->orderBy('name')->get(['id', 'name']);
        $childNames = $allChildren->pluck('name', 'id');

        $weekDates = $weekDays->pluck('date')->all();

        $departures = DailyDeparture::query()
            ->whereIn('child_id', $weekChildren->pluck('id'))
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

        // Companions referenced by any „geht mit … mit" override this week (a companion
        // may be a child the user doesn't manage) — resolve their effective plans once so
        // the per-day loop can mirror the companion's time without a per-cell query.
        $companionPlans = EffectivePlan::forMany(
            $departures->where('planned_method', DepartureMethod::WithChild)
                ->pluck('companion_child_id')->filter()->all(),
            $weekDates,
        );

        $absences = Absence::query()
            ->whereIn('child_id', $weekChildren->pluck('id'))
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (Absence $a) => $a->child_id.'|'.$a->date->toDateString());

        $todayString = $today->toDateString();
        $toMinutes = fn (string $time): int => ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
        $shortTime = fn (?string $time): ?string => $time ? substr($time, 0, 5) : null;

        // Excursions in this week: a per-weekday list for the activities row, plus a
        // per-(child, date) map of the trips a child actually takes part in.
        $weekExcursions = Excursion::query()
            ->with('participants:id')
            ->whereIn('date', $weekDates)
            ->orderBy('depart_at')
            ->get();

        $activities = $weekDays->values()->map(fn (array $day) => $weekExcursions
            ->filter(fn (Excursion $e) => $e->date->toDateString() === $day['date'])
            ->map(fn (Excursion $e) => [
                'name' => $e->name,
                'depart_at' => $shortTime($e->depart_at),
                'return_at' => $shortTime($e->return_at),
            ])
            ->values())
            ->all();

        // Day program (lunch + activity + effective homework) per weekday.
        $programs = DailyProgram::query()
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyProgram $p) => $p->date->toDateString());
        $homeworkDefaults = HomeworkDefault::all()->keyBy('weekday');

        $program = $weekDays->values()->map(function (array $day, int $i) use ($programs, $homeworkDefaults) {
            $p = $programs->get($day['date']);
            $default = $homeworkDefaults->get($i + 1);
            [$hwStart, $hwEnd] = DailyProgram::effectiveHomework($p, $default);

            return [
                'lunch' => $p?->lunch,
                'activity' => $p?->activity,
                'homework_start' => $hwStart ? substr((string) $hwStart, 0, 5) : null,
                'homework_end' => $hwEnd ? substr((string) $hwEnd, 0, 5) : null,
            ];
        })->all();

        $excursionByChildDate = [];
        foreach ($weekExcursions as $excursion) {
            foreach ($excursion->participants as $participant) {
                $excursionByChildDate[$participant->id.'|'.$excursion->date->toDateString()] = [
                    'name' => $excursion->name,
                    'depart_at' => $shortTime($excursion->depart_at),
                    'return_at' => $shortTime($excursion->return_at),
                ];
            }
        }

        $currentWeek = $weekChildren->map(function (Child $child) use ($weekDays, $departures, $absences, $todayString, $excursionByChildDate, $toMinutes, $childNames, $companionPlans) {
            $byWeekday = $child->weeklySchedules->keyBy('weekday');
            $canManage = true;

            $days = $weekDays->values()->map(function (array $day, int $i) use ($child, $byWeekday, $departures, $absences, $todayString, $canManage, $excursionByChildDate, $toMinutes, $childNames, $companionPlans) {
                $schedule = $byWeekday->get($i + 1);
                $stdTime = $schedule && $schedule->planned_time ? substr((string) $schedule->planned_time, 0, 5) : null;
                $stdMethod = $schedule?->method?->value;

                $departure = $departures->get($child->id.'|'.$day['date']);
                $time = $departure
                    ? ($departure->planned_time ? substr((string) $departure->planned_time, 0, 5) : null)
                    : $stdTime;
                $method = $departure ? $departure->planned_method?->value : $stdMethod;
                $status = $departure?->status;

                // „geht mit … mit": mirror the companion's effective time + carry its state.
                $companion = null;
                if ($departure && $method === DepartureMethod::WithChild->value && $departure->companion_child_id) {
                    $companion = [
                        'id' => $departure->companion_child_id,
                        'name' => $childNames[$departure->companion_child_id] ?? '',
                        'confirmed' => $departure->companion_confirmed,
                    ];
                    $time = $companionPlans[$departure->companion_child_id.'|'.$day['date']]['time'] ?? null;
                }

                $departed = $status !== null && $status !== DepartureStatus::Present;

                $adjusted = $departure !== null && ($time !== $stdTime || $method !== $stdMethod);

                $absence = $absences->get($child->id.'|'.$day['date']);

                // Is the child on a trip this day, and does the pickup fall inside it?
                $excursion = $excursionByChildDate[$child->id.'|'.$day['date']] ?? null;
                $conflict = false;
                if ($excursion && $time && $excursion['return_at']) {
                    $pickup = $toMinutes($time);
                    $departAt = $excursion['depart_at'] ? $toMinutes($excursion['depart_at']) : 0;
                    $conflict = $pickup >= $departAt && $pickup < $toMinutes($excursion['return_at']);
                }

                // Age the child turns this day, or null if it's not their birthday.
                $dob = $child->date_of_birth;
                $birthday = $dob && $dob->format('m-d') === substr($day['date'], 5)
                    ? ((int) substr($day['date'], 0, 4)) - $dob->year
                    : null;

                return [
                    'date' => $day['date'],
                    'time' => $time,
                    'method' => $method,
                    // The „geht allein" time qualifier (bis/um/ab): from the override if
                    // there is one, otherwise the Stammplan's.
                    'qualifier' => $method === DepartureMethod::SentHome->value
                        ? ($departure ? $departure->time_qualifier?->value : $schedule?->time_qualifier?->value)
                        : null,
                    // Companion for „geht mit … mit": { name, confirmed: null|true|false }.
                    'companion' => $companion,
                    // Shown on the cell: the override's own note, or the Stammplan comment.
                    'comment' => $adjusted ? $departure?->note : $schedule?->comment,
                    // Pre-fills the editor; an override defaults to the standard comment.
                    'note' => $departure?->note ?? $schedule?->comment,
                    'adjusted' => $adjusted,
                    'past' => $day['date'] < $todayString,
                    'editable' => $canManage && $day['date'] >= $todayString && ! $departed,
                    'excursion' => $excursion,
                    'conflict' => $conflict,
                    'birthday' => $birthday,
                    // Absence for this day: reason value + label + comment, or null.
                    'absent' => $absence ? ['reason' => $absence->reason->value, 'label' => $absence->reason->label(), 'comment' => $absence->comment] : null,
                ];
            });

            return [
                'id' => $child->id,
                'name' => $child->name,
                'can_manage' => $canManage,
                'days' => $days,
            ];
        });

        // Everyone reported away this week, per weekday — shown under the whole-week grid
        // (where absent children no longer appear at a time).
        $absencesByDate = Absence::query()
            ->with('child:id,name')
            ->whereIn('date', $weekDates)
            ->get()
            ->groupBy(fn (Absence $a) => $a->date->toDateString());

        $weekAbsences = $weekDays->values()->map(fn (array $day) => ($absencesByDate->get($day['date']) ?? collect())
            ->sortBy(fn (Absence $a) => $a->child->name)
            ->map(fn (Absence $a) => [
                'name' => $a->child->name,
                'label' => $a->reason->label(),
                'comment' => $a->comment,
            ])->values()->all())
            ->all();

        // Each child's effective pickup time per date this week (override → Stammplan),
        // so the companion picker can show „… wird übernommen (15:30)" and only offer
        // children who can actually be a companion. A with_child override has no own
        // time (it mirrors someone else) and an absent child has no pickup — both stay
        // blank, mirroring the AdjustDayRequest::after() „companion_unavailable" check.
        $allOverrides = DailyDeparture::query()
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

        $absentKeys = $absencesByDate->flatMap(
            fn ($absences, string $date) => $absences->map(fn (Absence $a) => $a->child_id.'|'.$date)
        )->flip();

        $childTimes = $allChildren->mapWithKeys(function (Child $c) use ($weekDays, $allOverrides, $absentKeys) {
            $byWeekday = $c->weeklySchedules->keyBy('weekday');
            $times = [];
            foreach ($weekDays->values() as $i => $day) {
                if ($absentKeys->has($c->id.'|'.$day['date'])) {
                    continue;
                }
                $override = $allOverrides->get($c->id.'|'.$day['date']);
                $raw = $override
                    ? $override->planned_time
                    : $byWeekday->get($i + 1)?->planned_time;
                if ($raw) {
                    $times[$day['date']] = substr((string) $raw, 0, 5);
                }
            }

            return [$c->id => $times];
        });

        return Inertia::render('WeeklyPlan/Index', [
            'week' => $week,
            'weekDays' => $weekDays,
            'currentWeek' => $currentWeek,
            'weekAbsences' => $weekAbsences,
            'activities' => $activities,
            'program' => $program,
            'weekTimetable' => $this->weekTimetable($weekDays, $excursionByChildDate, $program, $activities),
            'children' => $allChildren->map(fn (Child $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'times' => $childTimes[$c->id] ?? [],
            ])->all(),
            'companionNotes' => CompanionNotes::for($user, $weekDates),
            'methodOptions' => collect(DepartureMethod::cases())
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->all(),
            'qualifierOptions' => collect(TimeQualifier::cases())
                ->map(fn (TimeQualifier $q) => ['value' => $q->value, 'label' => $q->label(), 'prefix' => $q->prefix()])
                ->all(),
        ]);
    }

    /**
     * The selected week's *effective* timetable (Stammplan + same-day overrides)
     * for all children, in the same 30-minute time-slot shape as the standard plan.
     *
     * @param  array<string, array>  $excursionByChildDate
     * @param  array<int, array>  $program
     * @param  array<int, array>  $activities
     * @return array<int, array{time: string, days: array}>
     */
    private function weekTimetable(Collection $weekDays, array $excursionByChildDate, array $program, array $activities): array
    {
        $children = Child::query()->with('weeklySchedules')->orderBy('name')->get(['id', 'name']);
        $names = $children->pluck('name', 'id');
        $weekDates = $weekDays->pluck('date')->all();

        $departures = DailyDeparture::query()
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

        // Companions' effective plans, resolved once — the loop mirrors their time.
        $companionPlans = EffectivePlan::forMany(
            $departures->where('planned_method', DepartureMethod::WithChild)
                ->pluck('companion_child_id')->filter()->all(),
            $weekDates,
        );

        // Reported-away child-days are off the timeline (their override row is gone,
        // so without this they'd fall back to the Stammplan and reappear).
        $absentKeys = Absence::query()
            ->whereIn('date', $weekDates)
            ->get()
            ->map(fn (Absence $a) => $a->child_id.'|'.$a->date->toDateString())
            ->flip();

        $toMinutes = fn (string $time): int => ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
        $bucket = fn (int $minutes): int => intdiv($minutes, 30) * 30;
        $label = fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        // Per weekday (0=Mo … 4=Fr), each child's effective pickup for that date.
        $dayLists = array_fill(0, 5, []);
        $weekdayDays = $weekDays->values();
        $todayString = Carbon::today()->toDateString();

        foreach ($children as $child) {
            $byWeekday = $child->weeklySchedules->keyBy('weekday');

            foreach ($weekdayDays as $i => $day) {
                if ($absentKeys->has($child->id.'|'.$day['date'])) {
                    continue;
                }

                $schedule = $byWeekday->get($i + 1);
                $stdTime = $schedule?->planned_time;
                $departure = $departures->get($child->id.'|'.$day['date']);
                $method = $departure ? $departure->planned_method : $schedule?->method;
                $time = $departure && $departure->planned_time ? $departure->planned_time : $stdTime;

                // „geht mit … mit": the time is always mirrored from the companion, but
                // the arrangement itself is only shown to staff once the companion's
                // family has confirmed. Until then it reads as a normal pickup at that
                // synced time (see the board for the same rule).
                $companion = null;
                if ($departure && $method === DepartureMethod::WithChild && $departure->companion_child_id) {
                    $time = $companionPlans[$departure->companion_child_id.'|'.$day['date']]['time'] ?? null;
                    if ($departure->companion_confirmed === true) {
                        $companion = ['name' => $names[$departure->companion_child_id] ?? '', 'confirmed' => true];
                    } else {
                        $method = DepartureMethod::PickedUp;
                    }
                }

                if (! $time) {
                    continue;
                }

                $short = substr((string) $time, 0, 5);
                $stdShort = $stdTime ? substr((string) $stdTime, 0, 5) : null;
                $adjusted = $departure !== null
                    && ($short !== $stdShort || $method?->value !== $schedule?->method?->value);
                $departed = $departure?->status !== null && $departure?->status !== DepartureStatus::Present;

                // „geht allein" prefix (bis/ab); the default „genau um" stays implicit.
                // Use the override's qualifier if there is one, else the Stammplan's.
                $qualifier = $method?->value === DepartureMethod::SentHome->value
                    ? ($departure ? $departure->time_qualifier : $schedule?->time_qualifier)
                    : null;

                $dayLists[$i][] = [
                    'id' => $child->id,
                    'name' => $child->name,
                    'time' => $short,
                    'method' => $method?->value,
                    'qualifier_prefix' => $qualifier && $qualifier !== TimeQualifier::At
                        ? $qualifier->prefix()
                        : null,
                    'companion' => $companion,
                    'comment' => $adjusted ? $departure?->note : $schedule?->comment,
                    'note' => $departure?->note ?? $schedule?->comment,
                    'adjusted' => $adjusted,
                    'excursion' => isset($excursionByChildDate[$child->id.'|'.$day['date']]),
                    'date' => $day['date'],
                    'editable' => $day['date'] >= $todayString && ! $departed,
                    'minutes' => $toMinutes($short),
                ];
            }
        }

        // Extend the range to cover homework + excursion windows, so their bands
        // always have slot rows to span even when no child leaves during them.
        $starts = collect($dayLists)->flatten(1)->pluck('minutes')->all();
        $ends = $starts;
        for ($i = 0; $i < 5; $i++) {
            $hwStart = $program[$i]['homework_start'] ?? null;
            if ($hwStart) {
                $starts[] = $toMinutes($hwStart);
                $ends[] = $toMinutes($program[$i]['homework_end'] ?? $hwStart) - 1;
            }
            foreach ($activities[$i] ?? [] as $excursion) {
                if (! empty($excursion['depart_at'])) {
                    $starts[] = $toMinutes($excursion['depart_at']);
                }
                if (! empty($excursion['return_at'])) {
                    $ends[] = $toMinutes($excursion['return_at']) - 1;
                }
            }
        }

        if (empty($starts)) {
            return [];
        }

        $low = $bucket(min($starts));
        $high = $bucket(max(max($starts), empty($ends) ? min($starts) : max($ends)));

        $rows = [];
        for ($minutes = $low; $minutes <= $high; $minutes += 30) {
            $days = [];
            for ($i = 0; $i < 5; $i++) {
                $days[] = collect($dayLists[$i])
                    ->filter(fn (array $e) => $bucket($e['minutes']) === $minutes)
                    ->sortBy('name')
                    ->map(fn (array $e) => Arr::except($e, ['minutes']))
                    ->values();
            }
            $rows[] = ['time' => $label($minutes), 'days' => $days];
        }

        return $rows;
    }
}
