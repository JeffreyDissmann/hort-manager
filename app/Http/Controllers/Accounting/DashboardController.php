<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/** Admin-only home of the accounting world: balances plus what needs attention. */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $newest = Booking::where('status', BookingStatus::Confirmed)->max('booking_date');
        // Anchor the comparisons to the data's own latest date (the newest booking),
        // not the wall clock: previous quarter-end and previous year-end from there.
        $reference = $newest ? CarbonImmutable::parse($newest) : CarbonImmutable::now();
        $prevQuarterEnd = $reference->startOfQuarter()->subDay();
        $prevYearEnd = $reference->startOfYear()->subDay();

        $series = $this->balanceSeries($reference);

        return Inertia::render('Accounting/Dashboard', [
            // Balances at three points in time, summed in the DB (no per-account N+1).
            'accounts' => Account::query()
                ->withSum(['bookings as confirmed_cents' => fn (Builder $q) => $q->confirmed()], 'amount_cents')
                ->withSum(['bookings as quarter_cents' => fn (Builder $q) => $q->confirmed()->whereDate('booking_date', '<=', $prevQuarterEnd->toDateString())], 'amount_cents')
                ->withSum(['bookings as year_cents' => fn (Builder $q) => $q->confirmed()->whereDate('booking_date', '<=', $prevYearEnd->toDateString())], 'amount_cents')
                ->orderBy('name')
                ->get()
                ->map(fn (Account $a): array => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'balance_cents' => $a->opening_balance_cents + (int) $a->confirmed_cents,
                    'balance_quarter_cents' => $a->opening_balance_cents + (int) $a->quarter_cents,
                    'balance_year_cents' => $a->opening_balance_cents + (int) $a->year_cents,
                    // Running month-end balance through the reference year (for the sparkline).
                    'balance_series' => $series[$a->id] ?? [],
                ]),
            'periods' => [
                'quarter' => $prevQuarterEnd->toDateString(),
                'year' => $prevYearEnd->toDateString(),
            ],
            // Unconfirmed bookings still awaiting review.
            'reviewCount' => Booking::needsReview()->count(),
            // The data is accurate up to the newest confirmed booking.
            'asOf' => $newest ? CarbonImmutable::parse($newest)->toDateString() : null,
        ]);
    }

    /**
     * Per-account running month-end balance through the reference year (Jan … the
     * reference month), for the dashboard sparklines. One query, bucketed in PHP.
     *
     * @return array<int, list<int>> account id → month-end balances
     */
    private function balanceSeries(CarbonImmutable $reference): array
    {
        $year = $reference->year;
        $lastMonth = $reference->month;

        $accounts = Account::query()->get(['id', 'opening_balance_cents']);

        // Balance carried into the year, and each in-year month's delta, per account.
        $base = [];
        $monthly = [];
        Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->get(['account_id', 'amount_cents', 'booking_date'])
            ->each(function (Booking $b) use (&$base, &$monthly, $year): void {
                if ($b->booking_date->year < $year) {
                    $base[$b->account_id] = ($base[$b->account_id] ?? 0) + $b->amount_cents;
                } elseif ($b->booking_date->year === $year) {
                    $monthly[$b->account_id][$b->booking_date->month] = ($monthly[$b->account_id][$b->booking_date->month] ?? 0) + $b->amount_cents;
                }
            });

        $series = [];
        foreach ($accounts as $account) {
            $running = $account->opening_balance_cents + ($base[$account->id] ?? 0);
            $points = [];
            for ($m = 1; $m <= $lastMonth; $m++) {
                $running += $monthly[$account->id][$m] ?? 0;
                $points[] = $running;
            }
            $series[$account->id] = $points;
        }

        return $series;
    }
}
