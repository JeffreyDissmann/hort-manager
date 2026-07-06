<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WeeklySchedule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Stammplan (standard plan): every child's regular Mo–Fr pickup timetable,
 * the same every week. Read-only here — edited per child on Children/Edit.
 */
class StandardPlanController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('StandardPlan/Index', [
            'standard' => $this->standardTimetable(),
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
