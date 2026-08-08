<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AbsenceReason;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The queries behind „Statistik". Aggregates only — a number here never identifies a
 * single child, which is what lets the page exist at all.
 */
class HortStatistics
{
    /**
     * How the pickup times of a period are distributed, in the same half-hour slots the
     * Wochenplan uses. Two readings, because they answer different questions: the
     * *planned* time exists for every day and is what the Hort staffs against, while
     * the *actual* `left_at` is what happened — and only for the days someone marked
     * off, which is why the two can't be mixed into one series.
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
    public static function pickupTimes(Carbon $from, Carbon $to, string $basis = 'planned'): array
    {
        // „actual" reads `left_at` — when staff really marked the child off. Only days
        // that were marked can count, so a day nobody got round to is left out rather
        // than silently filled in with its plan.
        $actual = $basis === 'actual';
        $column = $actual ? 'left_at' : 'planned_time';

        $rows = DailyDeparture::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull($column)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)
                ->from('absences')
                ->whereColumn('absences.child_id', 'daily_departures.child_id')
                ->whereColumn('absences.date', 'daily_departures.date'))
            ->get([$column]);

        $slots = [];

        foreach ($rows as $row) {
            // `left_at` is a timestamp, `planned_time` a time — both reduce to „HH:MM".
            $time = $actual ? $row->left_at->format('H:i') : (string) $row->planned_time;
            $slot = self::slot($time);
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

    /**
     * The gaps worth fixing, counted as they stand today — deliberately not tied to the
     * page's Zeitraum: „drei Kinder haben keinen Stammplan" is true now or not at all.
     *
     * Every entry is something someone can go and do; a check that only produces
     * interest belongs in a chart instead.
     *
     * @return array<string, int>
     */
    public static function gaps(): array
    {
        $today = Carbon::today();

        return [
            // No Stammplan means no board, no Wochenplan, nothing to change.
            'children_without_plan' => Child::query()->activeOn($today)->withoutSchedule()->count(),
            // Nobody to notify and nobody who may edit the child.
            'children_without_guardian' => Child::query()->activeOn($today)
                ->whereDoesntHave('guardians')->count(),
            // They still get web push, but every Slack message misses them.
            'guardians_without_slack' => User::query()
                ->whereNull('slack_id')->whereHas('children')->count(),
            // Accounts belonging to nobody: not staff, not an admin, and not a
            // guardian. A Slack import leaves these behind when someone's child has
            // long left, and they keep receiving whatever goes to „alle Eltern".
            'orphaned_accounts' => User::query()
                ->where('role', UserRole::Parent)
                ->where('is_admin', false)
                ->whereDoesntHave('children')
                ->count(),
            // Silent until someone looks: a stale Slack id or a dead push endpoint
            // piles up here and nothing in the app ever says so.
            'failed_jobs' => DB::table('failed_jobs')->count(),
            // Invitations to coming trips nobody has answered. Straight off the pivot:
            // `wherePivotNull` is a relation method and doesn't survive a withCount
            // closure, which silently counted nothing at all.
            'open_excursion_answers' => DB::table('child_excursion')
                ->whereNull('response')
                ->whereIn('excursion_id', Excursion::query()
                    ->whereDate('date', '>=', $today)
                    ->select('id'))
                ->count(),
        ];
    }

    /**
     * What is actually stored, and since when. The app writes one row per child per
     * day and never stops, so „wie viel liegt hier eigentlich" is the question that
     * makes an Aufbewahrungsfrist a real decision rather than an abstract one.
     *
     * @return array{records: list<array{key: string, count: int, oldest: string|null}>, children_active: int, children_former: int, users: int, users_with_slack: int, database_bytes: int|null}
     */
    public static function inventory(): array
    {
        $today = Carbon::today();

        $records = [
            ['key' => 'departures', 'table' => 'daily_departures', 'column' => 'date'],
            ['key' => 'absences', 'table' => 'absences', 'column' => 'date'],
            ['key' => 'activity_log', 'table' => 'activity_log', 'column' => 'created_at'],
        ];

        return [
            'records' => array_map(fn (array $record): array => [
                'key' => $record['key'],
                'count' => DB::table($record['table'])->count(),
                // A date string either way — `created_at` carries a time we don't need.
                'oldest' => substr((string) DB::table($record['table'])->min($record['column']), 0, 10) ?: null,
            ], $records),
            'children_active' => Child::query()->activeOn($today)->count(),
            // History, not clutter: a child who left still belongs to their own year.
            'children_former' => Child::query()->whereNotNull('active_until')
                ->whereDate('active_until', '<', $today)->count(),
            'users' => User::query()->count(),
            'users_with_slack' => User::query()->whereNotNull('slack_id')->count(),
            'database_bytes' => self::databaseSize(),
        ];
    }

    /**
     * The SQLite file's size — the one number that says „this is what a backup costs".
     * Null when the database isn't a file (`:memory:` in tests, or another driver).
     */
    private static function databaseSize(): ?int
    {
        $path = DB::connection()->getDatabaseName();

        return is_string($path) && is_file($path) ? (filesize($path) ?: null) : null;
    }

    /** „15:17" → „15:00", „15:42" → „15:30" — the Wochenplan's half-hour grid. */
    private static function slot(string $time): string
    {
        $hour = substr($time, 0, 2);
        $minute = (int) substr($time, 3, 2) < 30 ? '00' : '30';

        return "{$hour}:{$minute}";
    }
}
