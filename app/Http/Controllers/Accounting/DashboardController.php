<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use Carbon\CarbonImmutable;
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

        return Inertia::render('Accounting/Dashboard', [
            'accounts' => Account::orderBy('name')->get(['id', 'name', 'opening_balance_cents'])
                ->map(fn (Account $a): array => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'balance_cents' => $a->balanceCents(),
                    'balance_quarter_cents' => $a->balanceCentsAsOf($prevQuarterEnd->toDateString()),
                    'balance_year_cents' => $a->balanceCentsAsOf($prevYearEnd->toDateString()),
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
}
