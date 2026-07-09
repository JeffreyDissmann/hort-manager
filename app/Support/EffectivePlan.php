<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DailyDeparture;
use App\Models\WeeklySchedule;
use Illuminate\Support\Carbon;

/**
 * Resolves a child's *effective* pickup for a date — the same-day override
 * (DailyDeparture) if one exists, otherwise the Stammplan (WeeklySchedule) for
 * that weekday. Companion mirroring is intentionally NOT resolved here: a
 * „geht mit einem anderen Kind mit" plan returns its own null time + companion id,
 * and the caller mirrors the companion's time (one level, chains are forbidden).
 */
class EffectivePlan
{
    /**
     * @return array{time: ?string, method: ?string, qualifier: ?string, companion_child_id: ?int}
     */
    public static function for(int $childId, string $date): array
    {
        $override = DailyDeparture::query()
            ->where('child_id', $childId)
            ->where('date', $date)
            ->first();

        if ($override !== null) {
            return [
                'time' => self::short($override->planned_time),
                'method' => $override->planned_method?->value,
                'qualifier' => $override->time_qualifier?->value,
                'companion_child_id' => $override->companion_child_id,
            ];
        }

        $weekday = Carbon::parse($date)->isoWeekday(); // 1 (Mon) … 7 (Sun)
        $schedule = WeeklySchedule::query()
            ->where('child_id', $childId)
            ->where('weekday', $weekday)
            ->first();

        return [
            'time' => self::short($schedule?->planned_time),
            'method' => $schedule?->method?->value,
            'qualifier' => null,
            'companion_child_id' => null,
        ];
    }

    /**
     * Batch variant of {@see self::for()} for resolving many child/date pairs without
     * an N+1: preloads every relevant override and Stammplan row in two queries, then
     * resolves each pair in memory. Keyed „{childId}|{date}".
     *
     * @param  array<int, int>  $childIds
     * @param  array<int, string>  $dates
     * @return array<string, array{time: ?string, method: ?string, qualifier: ?string, companion_child_id: ?int}>
     */
    public static function forMany(array $childIds, array $dates): array
    {
        $childIds = array_values(array_unique($childIds));
        $dates = array_values(array_unique($dates));

        if (empty($childIds) || empty($dates)) {
            return [];
        }

        $overrides = DailyDeparture::query()
            ->whereIn('child_id', $childIds)
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(fn (DailyDeparture $d) => $d->child_id.'|'.$d->date->toDateString());

        $schedules = WeeklySchedule::query()
            ->whereIn('child_id', $childIds)
            ->get()
            ->keyBy(fn (WeeklySchedule $s) => $s->child_id.'|'.$s->weekday);

        $plans = [];
        foreach ($childIds as $childId) {
            foreach ($dates as $date) {
                $override = $overrides->get($childId.'|'.$date);
                if ($override !== null) {
                    $plans[$childId.'|'.$date] = [
                        'time' => self::short($override->planned_time),
                        'method' => $override->planned_method?->value,
                        'qualifier' => $override->time_qualifier?->value,
                        'companion_child_id' => $override->companion_child_id,
                    ];

                    continue;
                }

                $schedule = $schedules->get($childId.'|'.Carbon::parse($date)->isoWeekday());
                $plans[$childId.'|'.$date] = [
                    'time' => self::short($schedule?->planned_time),
                    'method' => $schedule?->method?->value,
                    'qualifier' => null,
                    'companion_child_id' => null,
                ];
            }
        }

        return $plans;
    }

    private static function short(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
