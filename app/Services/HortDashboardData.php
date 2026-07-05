<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
use App\Models\WeeklySchedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only snapshot of the Hort's departures for the TRMNL staff-room display:
 * today's pickup timeline plus a Mo–Fr week overview, focused on who leaves when.
 *
 * Purely derived (no writes) — the effective pickup is the same-day DailyDeparture
 * override when one exists, otherwise the Stammplan; rows are never seeded here.
 */
class HortDashboardData
{
    private const WEEKDAYS_LONG = [
        1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch',
        4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
    ];

    private const WEEKDAYS_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

    /** @return array<string, mixed> */
    public function build(): array
    {
        return [
            'generated_at' => now()->format('H:i'),
            'today' => $this->today(),
            'week' => $this->week(),
        ];
    }

    /** @return array<string, mixed> */
    private function today(): array
    {
        $date = $this->targetDate();
        $weekday = $date->dayOfWeekIso;
        $dateString = $date->toDateString();

        $absences = Absence::with('child:id,name')->where('date', $dateString)->get();

        $children = Child::query()
            ->whereHas('weeklySchedules', fn ($q) => $q->where('weekday', $weekday)->whereNotNull('planned_time'))
            ->with(['weeklySchedules' => fn ($q) => $q->where('weekday', $weekday)])
            ->whereNotIn('id', $absences->pluck('child_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $overrides = DailyDeparture::where('date', $dateString)
            ->whereIn('child_id', $children->pluck('id'))
            ->get()
            ->keyBy('child_id');

        $onExcursion = $this->excursionParticipantIds($dateString);

        $rows = $children->map(function (Child $child) use ($overrides, $onExcursion) {
            $schedule = $child->weeklySchedules->first();
            $override = $overrides->get($child->id);
            $time = $this->short($override?->planned_time ?? $schedule?->planned_time);

            if ($time === null) {
                return null;
            }

            $method = ($override?->planned_method ?? $schedule?->method)?->value;

            return [
                'time' => $time,
                'name' => $child->name,
                'method' => $method,
                'changed' => $this->isChanged($schedule, $override, $time, $method),
                'left' => ($override?->status ?? DepartureStatus::Present)->hasLeft(),
                'excursion' => $onExcursion->contains($child->id),
            ];
        })->filter();

        $present = $rows->reject(fn (array $row) => $row['left']);

        return [
            'weekday' => self::WEEKDAYS_LONG[$weekday],
            'date' => $date->format('d.m.Y'),
            'present_count' => $present->count(),
            'next_pickup' => $present->min('time'),
            'departures' => $this->groupByTime($rows, 'children', fn (Collection $kids) => $kids
                ->map(fn (array $row) => Arr::except($row, 'time'))->values()->all()),
            'absent' => $absences->map(fn (Absence $a) => [
                'name' => $a->child->name,
                'reason' => $a->reason->label(),
            ])->values()->all(),
            'program' => $this->program($date),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function week(): array
    {
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $todayString = Carbon::today()->toDateString();
        $days = collect(range(0, 4))->map(fn (int $i) => $weekStart->copy()->addDays($i));
        $dateStrings = $days->map->toDateString()->all();

        $children = Child::query()->with('weeklySchedules')->orderBy('name')->get(['id', 'name']);

        $overrides = DailyDeparture::whereIn('date', $dateStrings)->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

        $absentByDate = Absence::whereIn('date', $dateStrings)->get()
            ->groupBy(fn (Absence $a) => $a->date->toDateString())
            ->map->pluck('child_id');

        $excursionsByDate = Excursion::whereIn('date', $dateStrings)->orderBy('depart_at')->get()
            ->groupBy(fn (Excursion $e) => $e->date->toDateString());

        return $days->map(function (Carbon $day, int $i) use ($children, $overrides, $absentByDate, $excursionsByDate, $todayString) {
            $dateString = $day->toDateString();
            $absent = $absentByDate->get($dateString, collect());

            $rows = $children
                ->reject(fn (Child $child) => $absent->contains($child->id))
                ->map(function (Child $child) use ($overrides, $dateString, $i) {
                    $schedule = $child->weeklySchedules->firstWhere('weekday', $i + 1);
                    $override = $overrides->get($child->id.'|'.$dateString);
                    $time = $this->short($override?->planned_time ?? $schedule?->planned_time);

                    return $time === null ? null : ['time' => $time, 'name' => $child->name];
                })
                ->filter();

            return [
                'weekday' => self::WEEKDAYS_SHORT[$i],
                'date' => $day->format('d.m.'),
                'is_today' => $dateString === $todayString,
                'excursion' => $excursionsByDate->get($dateString)?->pluck('name')->implode(', '),
                'departures' => $this->groupByTime($rows, 'names', fn (Collection $kids) => $kids
                    ->pluck('name')->sort()->values()->all()),
            ];
        })->all();
    }

    /**
     * Group flat pickup rows by pickup time (ascending). Each group is shaped by
     * $shape and stored under $key ('children' for today, 'names' for the week).
     *
     * @param  Collection<int, array{time: string, name: string}>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupByTime(Collection $rows, string $key, callable $shape): array
    {
        return $rows
            ->groupBy('time')
            ->sortKeys()
            ->map(fn (Collection $kids, string $time) => ['time' => $time, $key => $shape($kids)])
            ->values()
            ->all();
    }

    private function isChanged(?WeeklySchedule $schedule, ?DailyDeparture $override, string $time, ?string $method): bool
    {
        return $override !== null
            && ($time !== $this->short($schedule?->planned_time) || $method !== $schedule?->method?->value);
    }

    /** @return array<string, mixed> */
    private function program(Carbon $date): array
    {
        $program = DailyProgram::where('date', $date->toDateString())->first();
        $default = HomeworkDefault::where('weekday', $date->dayOfWeekIso)->first();
        [$hwStart, $hwEnd] = DailyProgram::effectiveHomework($program, $default);

        return [
            'lunch' => $program?->lunch,
            'activity' => $program?->activity,
            'homework' => $hwStart ? $this->short($hwStart).'–'.$this->short($hwEnd) : null,
        ];
    }

    /** Child ids taking part in any excursion on the given date. @return Collection<int, int> */
    private function excursionParticipantIds(string $dateString): Collection
    {
        return Excursion::with('participants:id')
            ->where('date', $dateString)
            ->get()
            ->flatMap(fn (Excursion $e) => $e->participants->pluck('id'))
            ->unique()
            ->values();
    }

    /** Today, or the next weekday on weekends (matching the daily board). */
    private function targetDate(): Carbon
    {
        $date = Carbon::today();

        while ($date->dayOfWeekIso >= 6) {
            $date->addDay();
        }

        return $date;
    }

    private function short(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
