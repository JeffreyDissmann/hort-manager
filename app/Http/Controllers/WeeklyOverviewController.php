<?php

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
use App\Models\WeeklySchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyOverviewController extends Controller
{
    private const WEEKDAY_LABELS = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

    /**
     * The Wochenplan: this week's effective plan (Stammplan + same-day overrides)
     * on top, the standard Stammplan timetable below. Staff and a child's own
     * parents may adjust individual days of the current week from here.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();

        // Which week to show (?week=YYYY-MM-DD); defaults to the current week.
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        if ($request->filled('week')) {
            try {
                $weekStart = Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable) {
                // Fall back to the current week on an invalid value.
            }
        }

        $week = [
            'label' => 'KW '.$weekStart->isoWeek().' · '
                .$weekStart->format('d.m.').'–'.$weekStart->copy()->addDays(4)->format('d.m.'),
            'prev' => $weekStart->copy()->subWeek()->toDateString(),
            'next' => $weekStart->copy()->addWeek()->toDateString(),
            'is_current' => $weekStart->equalTo($today->copy()->startOfWeek(Carbon::MONDAY)),
        ];

        $weekDays = collect(range(0, 4))->map(function (int $i) use ($weekStart) {
            $date = $weekStart->copy()->addDays($i);

            return [
                'date' => $date->toDateString(),
                'label' => self::WEEKDAY_LABELS[$i],
                'date_label' => $date->format('d.m.'),
            ];
        });

        // "Diese Woche" is the user's editable view: a parent sees only their own
        // children, staff see all. The standard timetable below always shows everyone.
        $weekChildren = $user->isStaff()
            ? Child::query()->with('weeklySchedules')->orderBy('name')->get(['id', 'name'])
            : $user->children()->with('weeklySchedules')->orderBy('name')->get();

        $weekDates = $weekDays->pluck('date')->all();

        $departures = DailyDeparture::query()
            ->whereIn('child_id', $weekChildren->pluck('id'))
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

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
            $hwStart = $p?->homework_start ?? $default?->start_time;
            $hwEnd = $p?->homework_end ?? $default?->end_time;

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

        $currentWeek = $weekChildren->map(function (Child $child) use ($weekDays, $departures, $todayString, $excursionByChildDate, $toMinutes) {
            $byWeekday = $child->weeklySchedules->keyBy('weekday');
            $canManage = true;

            $days = $weekDays->values()->map(function (array $day, int $i) use ($child, $byWeekday, $departures, $todayString, $canManage, $excursionByChildDate, $toMinutes) {
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

                // Is the child on a trip this day, and does the pickup fall inside it?
                $excursion = $excursionByChildDate[$child->id.'|'.$day['date']] ?? null;
                $conflict = false;
                if ($excursion && $time && $excursion['return_at']) {
                    $pickup = $toMinutes($time);
                    $departAt = $excursion['depart_at'] ? $toMinutes($excursion['depart_at']) : 0;
                    $conflict = $pickup >= $departAt && $pickup < $toMinutes($excursion['return_at']);
                }

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
            'standard' => $this->standardTimetable(),
            'methodOptions' => collect(DepartureMethod::cases())
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->all(),
        ]);
    }

    /**
     * The standard Stammplan as a 30-minute time-slot timetable (Mo–Fr).
     *
     * @return array<int, array{time: string, days: array}>
     */
    private function standardTimetable(): array
    {
        $schedules = WeeklySchedule::query()
            ->with('child:id,name')
            ->whereNotNull('planned_time')
            ->get();

        $toMinutes = fn (string $time): int => ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
        $bucket = fn (int $minutes): int => intdiv($minutes, 30) * 30;
        $label = fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        $buckets = $schedules->map(fn (WeeklySchedule $s) => $bucket($toMinutes($s->planned_time)));

        if ($buckets->isEmpty()) {
            return [];
        }

        $rows = [];
        for ($minutes = $buckets->min(); $minutes <= $buckets->max(); $minutes += 30) {
            $days = [];
            for ($weekday = 1; $weekday <= 5; $weekday++) {
                $days[] = $schedules
                    ->filter(fn (WeeklySchedule $s) => $s->weekday === $weekday
                        && $bucket($toMinutes($s->planned_time)) === $minutes)
                    ->sortBy(fn (WeeklySchedule $s) => $s->child->name)
                    ->map(fn (WeeklySchedule $s) => [
                        'id' => $s->child->id,
                        'name' => $s->child->name,
                        'method' => $s->method?->value,
                        'comment' => $s->comment,
                    ])
                    ->values();
            }

            $rows[] = ['time' => $label($minutes), 'days' => $days];
        }

        return $rows;
    }
}
