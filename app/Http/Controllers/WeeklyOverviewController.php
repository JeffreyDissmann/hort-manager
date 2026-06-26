<?php

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Child;
use App\Models\DailyDeparture;
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
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        $weekDays = collect(range(0, 4))->map(function (int $i) use ($weekStart) {
            $date = $weekStart->copy()->addDays($i);

            return [
                'date' => $date->toDateString(),
                'label' => self::WEEKDAY_LABELS[$i],
                'date_label' => $date->format('d.m.'),
            ];
        });

        $children = Child::query()->with('weeklySchedules')->orderBy('name')->get(['id', 'name']);
        $weekDates = $weekDays->pluck('date')->all();

        $departures = DailyDeparture::query()
            ->whereIn('child_id', $children->pluck('id'))
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

        $myChildIds = $user->isStaff() ? null : $user->children()->pluck('children.id');
        $todayString = $today->toDateString();

        $currentWeek = $children->map(function (Child $child) use ($weekDays, $departures, $todayString, $user, $myChildIds) {
            $byWeekday = $child->weeklySchedules->keyBy('weekday');
            $canManage = $user->isStaff() || ($myChildIds?->contains($child->id) ?? false);

            $days = $weekDays->values()->map(function (array $day, int $i) use ($child, $byWeekday, $departures, $todayString, $canManage) {
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

                return [
                    'date' => $day['date'],
                    'time' => $time,
                    'method' => $method,
                    'adjusted' => $departure !== null && ($time !== $stdTime || $method !== $stdMethod),
                    'editable' => $canManage && $day['date'] >= $todayString && ! $departed,
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
            'weekDays' => $weekDays,
            'currentWeek' => $currentWeek,
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
                    ])
                    ->values();
            }

            $rows[] = ['time' => $label($minutes), 'days' => $days];
        }

        return $rows;
    }
}
