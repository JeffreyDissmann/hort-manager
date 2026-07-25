<?php

declare(strict_types=1);

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
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
    $admin = User::factory()->admin()->accountingWriter()->create();
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
    $admin = User::factory()->admin()->accountingWriter()->create();
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
    $admin = User::factory()->admin()->accountingWriter()->create();
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
    $admin = User::factory()->admin()->accountingWriter()->create();
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

it('converts an imported booking into a transfer, reusing it as the out leg', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $bank = Account::factory()->create(['name' => 'Hort-Konto']);
    $cash = Account::factory()->create(['name' => 'Bar-Kasse']);

    // An imported cash withdrawal: −50,00 on the bank account, awaiting review.
    $booking = Booking::factory()->suggested()->create([
        'account_id' => $bank->id,
        'amount_cents' => -5000,
        'category_id' => null,
        'purpose' => 'BARGELDAUSZAHLUNG GELDAUTOMAT',
    ]);

    $this->patch("/accounting/bookings/{$booking->id}/review", [
        'action' => 'transfer',
        'to_account_id' => $cash->id,
    ])->assertRedirect();

    // The original line is reused as a transfer leg (no category), plus one matching leg.
    $booking->refresh();
    expect($booking->kind)->toBe(BookingKind::Transfer)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->category_id)->toBeNull()
        ->and($booking->transfer_id)->not->toBeNull();

    $transfer = Transfer::first();
    expect($transfer->out_booking_id)->toBe($booking->id)          // the −50 leg on the bank
        ->and($transfer->outBooking->account_id)->toBe($bank->id)
        ->and($transfer->inBooking->account_id)->toBe($cash->id)   // the +50 leg on the cash box
        ->and($transfer->inBooking->amount_cents)->toBe(5000);

    // Exactly two legs, netting to zero — the money isn't double-counted.
    expect(Booking::where('kind', BookingKind::Transfer)->count())->toBe(2)
        ->and((int) Booking::where('kind', BookingKind::Transfer)->sum('amount_cents'))->toBe(0);
});

it('reuses a positive (deposit) booking as the in leg', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $bank = Account::factory()->create();
    $cash = Account::factory()->create();

    // A cash deposit onto the bank account: +100,00.
    $booking = Booking::factory()->suggested()->create(['account_id' => $bank->id, 'amount_cents' => 10000, 'category_id' => null]);

    $this->patch("/accounting/bookings/{$booking->id}/review", ['action' => 'transfer', 'to_account_id' => $cash->id]);

    $transfer = Transfer::first();
    expect($transfer->in_booking_id)->toBe($booking->id)           // +100 stays the target leg
        ->and($transfer->outBooking->account_id)->toBe($cash->id)  // −100 leg on the cash box
        ->and($transfer->outBooking->amount_cents)->toBe(-10000);
});

it('rejects converting to the booking’s own account', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $bank = Account::factory()->create();
    $booking = Booking::factory()->suggested()->create(['account_id' => $bank->id, 'amount_cents' => -5000]);

    $this->patch("/accounting/bookings/{$booking->id}/review", ['action' => 'transfer', 'to_account_id' => $bank->id])
        ->assertSessionHasErrors('to_account_id');

    expect(Transfer::count())->toBe(0)
        ->and($booking->refresh()->kind)->not->toBe(BookingKind::Transfer);
});

it('requires a target account when converting to a transfer', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $booking = Booking::factory()->suggested()->create(['amount_cents' => -5000]);

    $this->patch("/accounting/bookings/{$booking->id}/review", ['action' => 'transfer'])
        ->assertSessionHasErrors('to_account_id');
});

it('converts an existing (confirmed) booking to a transfer from the edit window', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $bank = Account::factory()->create(['name' => 'Hort-Konto']);
    $cash = Account::factory()->create(['name' => 'Bar-Kasse']);
    // A confirmed expense that turns out to be a cash withdrawal.
    $booking = Booking::factory()->expense()->create(['account_id' => $bank->id, 'amount_cents' => -5000]);

    $this->post("/accounting/bookings/{$booking->id}/convert-transfer", ['to_account_id' => $cash->id])
        ->assertRedirect('/accounting/bookings');

    $booking->refresh();
    expect($booking->kind)->toBe(BookingKind::Transfer)
        ->and($booking->transfer_id)->not->toBeNull();

    $transfer = Transfer::first();
    expect($transfer->outBooking->account_id)->toBe($bank->id)    // −50 stays on the bank
        ->and($transfer->inBooking->account_id)->toBe($cash->id)  // +50 on the cash box
        ->and($transfer->inBooking->amount_cents)->toBe(5000)
        ->and(Booking::where('kind', BookingKind::Transfer)->count())->toBe(2);
});

it('rejects converting to the booking’s own account from the edit window', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $bank = Account::factory()->create();
    $booking = Booking::factory()->expense()->create(['account_id' => $bank->id, 'amount_cents' => -5000]);

    $this->post("/accounting/bookings/{$booking->id}/convert-transfer", ['to_account_id' => $bank->id])
        ->assertSessionHasErrors('to_account_id');

    expect(Transfer::count())->toBe(0);
});

it('refuses to convert a booking that is already a transfer leg', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $from = Account::factory()->create();
    $to = Account::factory()->create();
    $other = Account::factory()->create();
    Transfer::record(fromAccountId: $from->id, toAccountId: $to->id, amountCents: 3000, bookingDate: '2026-04-10');
    $leg = Booking::where('kind', BookingKind::Transfer)->first();

    $this->post("/accounting/bookings/{$leg->id}/convert-transfer", ['to_account_id' => $other->id])
        ->assertForbidden();
});

it('forbids a read-only user from converting a booking to a transfer', function () {
    $this->actingAs(User::factory()->accountingReader()->create());
    $bank = Account::factory()->create();
    $cash = Account::factory()->create();
    $booking = Booking::factory()->expense()->create(['account_id' => $bank->id, 'amount_cents' => -5000]);

    $this->post("/accounting/bookings/{$booking->id}/convert-transfer", ['to_account_id' => $cash->id])
        ->assertForbidden();

    expect(Transfer::count())->toBe(0);
});

it('refuses to edit or update a single transfer leg', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
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
