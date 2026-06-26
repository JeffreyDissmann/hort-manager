<?php

namespace App\Http\Controllers;

use App\Models\WeeklySchedule;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyOverviewController extends Controller
{
    /**
     * Read-only weekly timetable (Wochenplan): a grid of 30-minute time slots
     * (from the earliest pickup to the latest) × Mo–Fr, with each child placed
     * in the slot they go home. Open to all authenticated users.
     */
    public function __invoke(): Response
    {
        $schedules = WeeklySchedule::query()
            ->with('child:id,name')
            ->whereNotNull('planned_time')
            ->get();

        $toMinutes = fn (string $time): int => ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
        $bucket = fn (int $minutes): int => intdiv($minutes, 30) * 30;
        $label = fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        $buckets = $schedules->map(fn (WeeklySchedule $s) => $bucket($toMinutes($s->planned_time)));

        $rows = [];

        if ($buckets->isNotEmpty()) {
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
                            'time' => substr((string) $s->planned_time, 0, 5),
                            'method' => $s->method?->value,
                        ])
                        ->values();
                }

                $rows[] = ['time' => $label($minutes), 'days' => $days];
            }
        }

        return Inertia::render('WeeklyPlan/Index', [
            'rows' => $rows,
        ]);
    }
}
