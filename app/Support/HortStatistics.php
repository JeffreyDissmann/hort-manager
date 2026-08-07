<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AbsenceReason;
use App\Models\Absence;
use App\Models\DailyDeparture;
use Illuminate\Support\Carbon;

/**
 * The queries behind „Statistik". Aggregates only — a number here never identifies a
 * single child, which is what lets the page exist at all.
 */
class HortStatistics
{
    /**
     * How the pickup times of a period are distributed, in the same half-hour slots the
     * Wochenplan uses. Counted by the *planned* time rather than by `left_at`: a plan
     * exists for every day including the ones nobody got round to marking off, and it
     * is the number the Hort staffs against.
     *
     * A day a child was reported away is not a pickup, so it gets its own bucket at the
     * far left (`time` = null) rather than being dropped: those children left before
     * the day began, and counting them is what makes the percentages honest.
     *
     * `remaining` is the share still there *after* that slot, so the series falls to 0:
     * „um 16:00 sind noch 40% da" is the number you staff the late shift against, and a
     * bar chart alone never answers it — the eye can't add columns.
     *
     * @return list<array{time: string|null, count: int, remaining: float}>
     */
    public static function pickupTimes(Carbon $from, Carbon $to): array
    {
        $rows = DailyDeparture::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('planned_time')
            ->whereNotExists(fn ($q) => $q->selectRaw(1)
                ->from('absences')
                ->whereColumn('absences.child_id', 'daily_departures.child_id')
                ->whereColumn('absences.date', 'daily_departures.date'))
            ->get(['planned_time']);

        $slots = [];

        foreach ($rows as $row) {
            $slot = self::slot((string) $row->planned_time);
            $slots[$slot] = ($slots[$slot] ?? 0) + 1;
        }

        ksort($slots);

        // „Gar nicht da" comes first — before any pickup time there is.
        $buckets = [];
        $absent = Absence::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->count();

        if ($absent > 0) {
            $buckets[] = ['time' => null, 'count' => $absent];
        }

        foreach ($slots as $time => $count) {
            $buckets[] = ['time' => $time, 'count' => $count];
        }

        $total = array_sum(array_column($buckets, 'count'));

        if ($total === 0) {
            return [];
        }

        $gone = 0;

        return array_map(function (array $bucket) use (&$gone, $total): array {
            $gone += $bucket['count'];

            return [...$bucket, 'remaining' => round(($total - $gone) / $total * 100, 1)];
        }, $buckets);
    }

    /**
     * Reported absences per month, kept apart by reason: „krank" is a wave that runs
     * through the whole Hort in February, „kommt nicht" is a family's own appointment.
     * Adding them up would hide exactly the difference worth seeing.
     *
     * @return list<array{month: string, sick: int, away: int}>
     */
    public static function absences(Carbon $from, Carbon $to): array
    {
        $rows = Absence::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'reason']);

        // Every month of the range, so a month nobody was ill still gets its gap.
        $months = [];

        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
            $months[$month->format('Y-m')] = ['sick' => 0, 'away' => 0];
        }

        foreach ($rows as $row) {
            $key = $row->date->format('Y-m');

            if (! isset($months[$key])) {
                continue;
            }

            $months[$key][$row->reason === AbsenceReason::Sick ? 'sick' : 'away']++;
        }

        return array_map(
            fn (string $month, array $counts): array => ['month' => $month, ...$counts],
            array_keys($months),
            array_values($months),
        );
    }

    /** „15:17" → „15:00", „15:42" → „15:30" — the Wochenplan's half-hour grid. */
    private static function slot(string $time): string
    {
        $hour = substr($time, 0, 2);
        $minute = (int) substr($time, 3, 2) < 30 ? '00' : '30';

        return "{$hour}:{$minute}";
    }
}
