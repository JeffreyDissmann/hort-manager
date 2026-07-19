<?php

declare(strict_types=1);

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\Accounting\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sums opening balance plus only confirmed bookings', function () {
    $account = Account::factory()->withOpeningBalance(10000)->create();

    Booking::factory()->for($account)->create(['amount_cents' => 5000]);   // confirmed +50
    Booking::factory()->for($account)->expense()->create(['amount_cents' => -2000]); // confirmed -20
    Booking::factory()->for($account)->draft()->create(['amount_cents' => 9999]); // draft: ignored

    // 100 opening + 50 - 20 = 130 €
    expect($account->balanceCents())->toBe(13000);
});

it('nests categories and inherits the root direction', function () {
    $root = Category::factory()->expense()->create(['name' => 'Betrieb']);
    $child = Category::factory()->childOf($root)->create(['name' => 'Miete']);

    expect($child->direction)->toBe(CategoryDirection::Expense)
        ->and($child->parent->is($root))->toBeTrue()
        ->and($root->children->pluck('id'))->toContain($child->id);
});

it('exposes draft and confirmed scopes', function () {
    Booking::factory()->count(2)->create();
    Booking::factory()->draft()->create();

    expect(Booking::confirmed()->count())->toBe(2)
        ->and(Booking::draft()->count())->toBe(1);
});

it('stamps the acting user as creator and updater', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $booking = Booking::factory()->create();

    expect($booking->created_by)->toBe($user->id)
        ->and($booking->updated_by)->toBe($user->id);
});

it('links the two legs of a transfer', function () {
    $out = Booking::factory()->create(['kind' => BookingKind::Transfer, 'category_id' => null, 'amount_cents' => -1000]);
    $in = Booking::factory()->create(['kind' => BookingKind::Transfer, 'category_id' => null, 'amount_cents' => 1000]);

    $transfer = Transfer::create(['out_booking_id' => $out->id, 'in_booking_id' => $in->id]);

    expect($transfer->outBooking->amount_cents)->toBe(-1000)
        ->and($transfer->inBooking->amount_cents)->toBe(1000)
        ->and($out->kind)->toBe(BookingKind::Transfer);
});

it('casts enums and money to their PHP types', function () {
    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);

    expect($booking->kind)->toBeInstanceOf(BookingKind::class)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->amount_cents)->toBeInt();
});
