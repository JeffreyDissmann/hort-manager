<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\User;

// The accounting charts through a real browser — they paint to a canvas now, so this
// is the only layer that can tell whether anything was actually drawn.

it('draws a sparkline per account on the dashboard', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $account = Account::factory()->withOpeningBalance(10000)->create();

    // Two months apart, so the series has more than one point to draw.
    Booking::factory()->create(['account_id' => $account->id, 'amount_cents' => 5000, 'booking_date' => '2026-04-01']);
    Booking::factory()->create(['account_id' => $account->id, 'amount_cents' => 3000, 'booking_date' => '2026-06-15']);

    $page = actAndVisit($admin, '/accounting');

    expect($page->script("document.querySelectorAll('table canvas').length"))->toBeGreaterThan(0);
});

it('draws the report donuts', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $account = Account::factory()->create();

    Booking::factory()->create(['account_id' => $account->id, 'amount_cents' => -5000, 'booking_date' => '2026-04-01']);
    Booking::factory()->create(['account_id' => $account->id, 'amount_cents' => 9000, 'booking_date' => '2026-04-02']);

    $page = actAndVisit($admin, '/accounting/reports');

    // One donut per direction, but a direction with nothing in it draws no canvas at
    // all — so this asserts the one that does have a booking behind it.
    expect($page->script("document.querySelectorAll('canvas').length"))->toBeGreaterThan(0);
});
