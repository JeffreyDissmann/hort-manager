<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Http\Controllers\Concerns\ResolvesWeek;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
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

        $weekDates = $weekDays->pluck('date')->all();

        $departures = DailyDeparture::query()
            ->whereIn('child_id', $weekChildren->pluck('id'))
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

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

        $currentWeek = $weekChildren->map(function (Child $child) use ($weekDays, $departures, $absences, $todayString, $excursionByChildDate, $toMinutes) {
            $byWeekday = $child->weeklySchedules->keyBy('weekday');
            $canManage = true;

            $days = $weekDays->values()->map(function (array $day, int $i) use ($child, $byWeekday, $departures, $absences, $todayString, $canManage, $excursionByChildDate, $toMinutes) {
                $schedule = $byWeekday->get($i + 1);
                $stdTime = $schedule && $schedule->planned_time ? substr((string) $schedule->planned_time, 0, 5) : null;
                $stdMethod = $schedule?->method?->value;

                $departure = $departures->get($child->id.'|'.$day['date']);
                $time = $departure
                    ? ($departure->planned_time ? substr((string) $departure->planned_time, 0, 5) : null)
                    : $stdTime;
                $method = $departure ? $departure->planned_method?->value : $stdMethod;
                $status = $departure?->status;

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
                    // Absence for this day: reason value + label, or null.
                    'absent' => $absence ? ['reason' => $absence->reason->value, 'label' => $absence->reason->label()] : null,
                ];
            });

            return [
                'id' => $child->id,
                'name' => $child->name,
                'can_manage' => $canManage,
                'days' => $days,
            ];
        });

        return Inertia::render('WeeklyPlan/Index', [
            'week' => $week,
            'weekDays' => $weekDays,
            'currentWeek' => $currentWeek,
            'activities' => $activities,
            'program' => $program,
            'weekTimetable' => $this->weekTimetable($weekDays, $excursionByChildDate, $program, $activities),
            'methodOptions' => collect(DepartureMethod::cases())
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
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
        $weekDates = $weekDays->pluck('date')->all();

        $departures = DailyDeparture::query()
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

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
                $schedule = $byWeekday->get($i + 1);
                $stdTime = $schedule?->planned_time;
                $departure = $departures->get($child->id.'|'.$day['date']);
                $time = $departure && $departure->planned_time ? $departure->planned_time : $stdTime;

                if (! $time) {
                    continue;
                }

                $short = substr((string) $time, 0, 5);
                $stdShort = $stdTime ? substr((string) $stdTime, 0, 5) : null;
                $method = $departure ? $departure->planned_method : $schedule?->method;
                $adjusted = $departure !== null
                    && ($short !== $stdShort || $method?->value !== $schedule?->method?->value);
                $departed = $departure?->status !== null && $departure?->status !== DepartureStatus::Present;

                $dayLists[$i][] = [
                    'id' => $child->id,
                    'name' => $child->name,
                    'time' => $short,
                    'method' => $method?->value,
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
