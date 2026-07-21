<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Support\Accounting\CategoryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only „Auswertung": a month × category pivot of the confirmed ledger for a
 * chosen year. Income and expense are shown as separate blocks (categories rolled
 * up over their subtree) with per-month and per-category totals and a monthly net.
 * Transfers are internal moves and are excluded.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $years = $this->availableYears();
        $year = $request->integer('year') ?: (int) $years->first();

        // Confirmed, categorised, non-transfer bookings for the year (a Hort's ledger
        // is small enough to bucket per category/month in PHP — no DB-specific SQL).
        $bookings = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->where('kind', '!=', BookingKind::Transfer)
            ->whereNotNull('category_id')
            ->whereYear('booking_date', $year)
            ->get(['category_id', 'amount_cents', 'booking_date']);

        $flat = collect(CategoryOptions::flat(onlyActive: false));
        $direction = $flat->pluck('direction', 'id');
        $parent = Category::pluck('parent_id', 'id');

        // Bucket each booking into its month, rolling the amount up into its category
        // and every ancestor so a parent row reflects its whole subtree.
        $totals = [];
        $incomeMonths = array_fill(1, 12, 0);
        $expenseMonths = array_fill(1, 12, 0);

        foreach ($bookings as $booking) {
            $month = $booking->booking_date->month;
            $cents = $booking->amount_cents;

            for ($node = $booking->category_id; $node !== null; $node = $parent[$node] ?? null) {
                $totals[$node][$month] = ($totals[$node][$month] ?? 0) + $cents;
            }

            if (($direction[$booking->category_id] ?? null) === CategoryDirection::Income->value) {
                $incomeMonths[$month] += $cents;
            } elseif (($direction[$booking->category_id] ?? null) === CategoryDirection::Expense->value) {
                $expenseMonths[$month] += $cents;
            }
        }

        $netMonths = collect(range(1, 12))->map(fn (int $m): int => $incomeMonths[$m] + $expenseMonths[$m]);

        return Inertia::render('Accounting/Reports/Index', [
            'year' => $year,
            'years' => $years,
            'monthLabels' => collect(range(1, 12))
                ->map(fn (int $m): string => CarbonImmutable::create($year, $m, 1)->translatedFormat('M')),
            'incomeRows' => $this->rows($flat, $totals, CategoryDirection::Income),
            'expenseRows' => $this->rows($flat, $totals, CategoryDirection::Expense),
            'incomeMonths' => array_values($incomeMonths),
            'expenseMonths' => array_values($expenseMonths),
            'netMonths' => $netMonths->values(),
            'incomeTotal' => array_sum($incomeMonths),
            'expenseTotal' => array_sum($expenseMonths),
            'netTotal' => $netMonths->sum(),
        ]);
    }

    /**
     * The category rows for one direction, in tree order, dropping categories with
     * no activity this year. Each row carries its 12 monthly sums and a row total.
     *
     * @param  Collection<int, array<string, mixed>>  $flat
     * @param  array<int, array<int, int>>  $totals
     * @return list<array{id:int, parent_id:?int, name:string, depth:int, months:list<int>, total:int}>
     */
    private function rows(Collection $flat, array $totals, CategoryDirection $direction): array
    {
        return $flat
            ->where('direction', $direction->value)
            ->map(fn (array $c): array => [
                'id' => $c['id'],
                'parent_id' => $c['parent_id'],
                'name' => $c['name'],
                'depth' => $c['depth'],
                'months' => collect(range(1, 12))->map(fn (int $m): int => $totals[$c['id']][$m] ?? 0)->all(),
                'total' => collect(range(1, 12))->sum(fn (int $m): int => $totals[$c['id']][$m] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['total'] !== 0)
            ->values()
            ->all();
    }

    /**
     * Every year from the oldest to the newest confirmed booking, newest first, so
     * the picker has no gaps (falls back to this year when the ledger is empty).
     *
     * @return Collection<int, int>
     */
    private function availableYears(): Collection
    {
        $confirmed = Booking::where('status', BookingStatus::Confirmed);
        $newest = $confirmed->clone()->max('booking_date');
        $oldest = $confirmed->clone()->min('booking_date');

        if ($newest === null) {
            return collect([(int) now()->year]);
        }

        return collect(range(CarbonImmutable::parse($newest)->year, CarbonImmutable::parse($oldest)->year));
    }
}
