<?php

declare(strict_types=1);

use App\Enums\BookingKind;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\Accounting\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids non-admins from creating a transfer', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/accounting/transfers/create')->assertForbidden();
});

it('creates two linked, opposite-signed legs with no category', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $from = Account::factory()->create(['name' => 'Bar-Kasse']);
    $to = Account::factory()->create(['name' => 'Hort-Konto']);

    $this->post('/accounting/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '200.00',
        'booking_date' => '2026-04-10',
    ])->assertRedirect('/accounting/bookings');

    $transfer = Transfer::first();
    expect($transfer)->not->toBeNull()
        ->and($transfer->outBooking->account_id)->toBe($from->id)
        ->and($transfer->outBooking->amount_cents)->toBe(-20000)
        ->and($transfer->inBooking->account_id)->toBe($to->id)
        ->and($transfer->inBooking->amount_cents)->toBe(20000)
        ->and($transfer->outBooking->kind)->toBe(BookingKind::Transfer)
        ->and($transfer->outBooking->category_id)->toBeNull()
        ->and($transfer->outBooking->transfer_id)->toBe($transfer->id);
});

it('nets to zero across both accounts', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $from = Account::factory()->create();
    $to = Account::factory()->create();

    $this->post('/accounting/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '75.50',
        'booking_date' => '2026-04-10',
    ]);

    expect($from->balanceCents() + $to->balanceCents())->toBe(0);
});

it('rejects a transfer to the same account', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $account = Account::factory()->create();

    $this->post('/accounting/transfers', [
        'from_account_id' => $account->id,
        'to_account_id' => $account->id,
        'amount' => '10',
        'booking_date' => '2026-04-10',
    ])->assertSessionHasErrors('from_account_id');
});

it('deleting one leg removes the whole transfer', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $from = Account::factory()->create();
    $to = Account::factory()->create();

    $this->post('/accounting/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '30',
        'booking_date' => '2026-04-10',
    ]);

    $leg = Booking::where('kind', BookingKind::Transfer)->first();
    $this->delete("/accounting/bookings/{$leg->id}")->assertRedirect();

    expect(Booking::where('kind', BookingKind::Transfer)->count())->toBe(0)
        ->and(Transfer::count())->toBe(0);
});

it('refuses to edit or update a single transfer leg', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $from = Account::factory()->create();
    $to = Account::factory()->create();

    $this->post('/accounting/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '30',
        'booking_date' => '2026-04-10',
    ]);

    $leg = Booking::where('kind', BookingKind::Transfer)->first();

    // Editing a leg would break the two-leg zero-sum invariant → forbidden.
    $this->get("/accounting/bookings/{$leg->id}/edit")->assertForbidden();
    $this->put("/accounting/bookings/{$leg->id}", [
        'account_id' => $from->id,
        'category_id' => Category::factory()->income()->create()->id,
        'amount' => '99',
        'booking_date' => '2026-04-10',
    ])->assertForbidden();

    // Untouched: still two transfer legs that net to zero.
    expect(Booking::where('kind', BookingKind::Transfer)->count())->toBe(2)
        ->and((int) Booking::where('kind', BookingKind::Transfer)->sum('amount_cents'))->toBe(0);
});
