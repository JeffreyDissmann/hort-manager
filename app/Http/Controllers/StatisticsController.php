<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\HortStatistics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Statistik" — what the Hort's own records add up to. Admin-only (the /admin group
 * gates it too). Everything here is read from data the app already keeps: no new
 * tables, no new writes, and nothing that names a single child.
 */
class StatisticsController extends Controller
{
    /** The ranges offered above the charts, as `key => [from, to]`. */
    public const RANGES = ['quarter', 'school-year', 'year'];

    public function __invoke(Request $request): Response
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        $range = in_array($request->query('range'), self::RANGES, true)
            ? $request->query('range')
            : 'quarter';

        [$from, $to] = $this->period($range);

        return Inertia::render('Statistics/Index', [
            'range' => $range,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'pickupTimes' => HortStatistics::pickupTimes($from, $to),
            'absences' => HortStatistics::absences($from, $to),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(string $range): array
    {
        $today = Carbon::today();

        return match ($range) {
            // A Hort year runs with the school year, so „dieses Jahr" for a Hort means
            // September to August — not January to December.
            'school-year' => [
                $today->copy()->month >= 9
                    ? $today->copy()->setDate($today->year, 9, 1)
                    : $today->copy()->setDate($today->year - 1, 9, 1),
                $today,
            ],
            'year' => [$today->copy()->startOfYear(), $today],
            default => [$today->copy()->subMonths(3), $today],
        };
    }
}
