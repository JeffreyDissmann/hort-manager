<?php

declare(strict_types=1);

use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('forbids non-admins from the report', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/accounting/reports')
        ->assertForbidden();
});

it('pivots confirmed bookings by category and month', function () {
    $admin = User::factory()->admin()->create();
    $income = Category::factory()->income()->create(['name' => 'Essensgeld']);
    $expense = Category::factory()->expense()->create(['name' => 'Miete']);

    Booking::factory()->create(['category_id' => $income->id, 'amount_cents' => 5000, 'booking_date' => '2026-01-15']);
    Booking::factory()->create(['category_id' => $income->id, 'amount_cents' => 5000, 'booking_date' => '2026-03-10']);
    Booking::factory()->expense()->create(['category_id' => $expense->id, 'amount_cents' => -352000, 'booking_date' => '2026-01-31']);
    // Excluded from the pivot: an unconfirmed draft.
    Booking::factory()->draft()->create(['category_id' => $income->id, 'amount_cents' => 9900, 'booking_date' => '2026-02-01']);

    $this->actingAs($admin)
        ->get('/accounting/reports?year=2026')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Reports/Index')
            ->where('year', 2026)
            ->where('incomeTotal', 10000)      // 5000 + 5000; the draft is excluded
            ->where('expenseTotal', -352000)
            ->where('netTotal', -342000)
            ->where('incomeMonths.0', 5000)    // January
            ->where('incomeMonths.1', 0)       // February (only the excluded draft)
            ->where('incomeMonths.2', 5000)    // March
            ->has('incomeRows', 1)
            ->has('expenseRows', 1));
});

it('defaults to the highest year and offers every year in the min–max range', function () {
    $admin = User::factory()->admin()->create();

    // Only 2024 and 2026 have bookings — 2025 should still appear (no gap).
    Booking::factory()->create(['booking_date' => '2024-05-01']);
    Booking::factory()->create(['booking_date' => '2026-05-01']);

    $this->actingAs($admin)
        ->get('/accounting/reports')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('year', 2026)
            ->where('years', [2026, 2025, 2024]));
});
