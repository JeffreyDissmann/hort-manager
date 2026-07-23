<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('forbids non-admins from the accounting dashboard', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/accounting')
        ->assertForbidden();
});

it('shows account balances at three points in time and what needs attention', function () {
    // Periods anchor to the newest booking (2026-07-10, Q3) → prev quarter-end
    // 2026-06-30, prev year-end 2025-12-31 — independent of the current date.
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->withOpeningBalance(10000)->create();
    $child = Child::factory()->create();

    Booking::factory()->create(['account_id' => $account->id, 'counterparty_child_id' => $child->id, 'amount_cents' => 5000, 'booking_date' => '2026-04-01']);
    Booking::factory()->create(['account_id' => $account->id, 'amount_cents' => 3000, 'booking_date' => '2026-05-15']);
    Booking::factory()->create(['account_id' => $account->id, 'amount_cents' => 2000, 'booking_date' => '2026-07-10']); // after the quarter-end
    // Unconfirmed → the "to review" alert; ignored for balance and "as of".
    Booking::factory()->suggested()->create(['account_id' => $account->id, 'amount_cents' => 9999, 'booking_date' => '2026-12-31']);

    $this->actingAs($admin)
        ->get('/accounting')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Dashboard')
            ->has('accounts', 1)
            ->where('accounts.0.balance_cents', 20000)         // 10000 + 5000 + 3000 + 2000
            ->where('accounts.0.balance_quarter_cents', 18000) // up to 2026-06-30 (excludes 07-10)
            ->where('accounts.0.balance_year_cents', 10000)    // up to 2025-12-31 (opening only)
            ->where('periods.quarter', '2026-06-30')
            ->where('periods.year', '2025-12-31')
            ->where('reviewCount', 1)
            ->where('asOf', '2026-07-10'));
});
