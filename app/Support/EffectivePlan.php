<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DailyDeparture;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
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

        // A Schließzeit has no day at all, and a Ferienbetreuung day has no Stammplan
        // behind it — there only a sign-up (a row that names the care day) is a plan.
        // Otherwise the weekday schedule would invent a pickup for a child who is on
        // holiday, and offer them to everyone else as a companion.
        $careDay = HolidayCareDay::query()->onDate($date)->first();

        if (HolidayPeriod::closesOn($date)) {
            return self::nothing();
        }

        if ($careDay !== null) {
            return $override?->holiday_care_day_id === $careDay->id
                ? self::fromOverride($override)
                : self::nothing();
        }

        if ($override !== null) {
            return self::fromOverride($override);
        }

        $weekday = Carbon::parse($date)->isoWeekday(); // 1 (Mon) … 7 (Sun)
        $schedule = WeeklySchedule::query()
            ->where('child_id', $childId)
            ->where('weekday', $weekday)
            ->first();

        return [
            'time' => self::short($schedule?->planned_time),
            'method' => $schedule?->method?->value,
            'qualifier' => $schedule?->time_qualifier?->value,
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

        // Days that have no Stammplan behind them (see {@see self::for()}).
        $careDays = HolidayCareDay::query()->whereIn('date', $dates)->get()
            ->keyBy(fn (HolidayCareDay $day): string => $day->date->toDateString());
        $closedDays = HolidayPeriod::closedDaysBetween(min($dates), max($dates));

        $plans = [];
        foreach ($childIds as $childId) {
            foreach ($dates as $date) {
                $override = $overrides->get($childId.'|'.$date);

                if (isset($closedDays[$date])) {
                    $plans[$childId.'|'.$date] = self::nothing();

                    continue;
                }

                if ($careDay = $careDays->get($date)) {
                    $plans[$childId.'|'.$date] = $override?->holiday_care_day_id === $careDay->id
                        ? self::fromOverride($override)
                        : self::nothing();

                    continue;
                }

                if ($override !== null) {
                    $plans[$childId.'|'.$date] = self::fromOverride($override);

                    continue;
                }

                $schedule = $schedules->get($childId.'|'.Carbon::parse($date)->isoWeekday());
                $plans[$childId.'|'.$date] = [
                    'time' => self::short($schedule?->planned_time),
                    'method' => $schedule?->method?->value,
                    'qualifier' => $schedule?->time_qualifier?->value,
                    'companion_child_id' => null,
                ];
            }
        }

        return $plans;
    }

    /**
     * @return array{time: ?string, method: ?string, qualifier: ?string, companion_child_id: ?int}
     */
    private static function fromOverride(DailyDeparture $override): array
    {
        return [
            'time' => self::short($override->planned_time),
            'method' => $override->planned_method?->value,
            'qualifier' => $override->time_qualifier?->value,
            'companion_child_id' => $override->companion_child_id,
        ];
    }

    /**
     * No plan at all — the day doesn't exist for this child.
     *
     * @return array{time: ?string, method: ?string, qualifier: ?string, companion_child_id: ?int}
     */
    private static function nothing(): array
    {
        return ['time' => null, 'method' => null, 'qualifier' => null, 'companion_child_id' => null];
    }

    private static function short(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
