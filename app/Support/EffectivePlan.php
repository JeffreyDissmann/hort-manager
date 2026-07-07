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

    private static function short(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
