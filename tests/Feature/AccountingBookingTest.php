<?php

declare(strict_types=1);

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('forbids non-admins from bookings', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/accounting/bookings')->assertForbidden();
});

it('lists bookings for admins', function () {
    $admin = User::factory()->admin()->create();
    Booking::factory()->create(['purpose' => 'Essensgeld April']);

    $this->actingAs($admin)
        ->get('/accounting/bookings')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Bookings/Index')
            ->has('bookings.data', 1)
            ->has('filterOptions.categories'));
});

it('creates an income booking as a positive confirmed amount', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $account = Account::factory()->create();
    $category = Category::factory()->income()->create();

    $this->post('/accounting/bookings', [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '50.00',
        'booking_date' => '2026-04-01',
        'counterparty_name' => 'Familie Muster',
    ])->assertRedirect('/accounting/bookings');

    $booking = Booking::first();
    expect($booking->amount_cents)->toBe(5000)
        ->and($booking->kind)->toBe(BookingKind::Income)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->counterparty_name)->toBe('Familie Muster');
});

it('creates an expense booking as a negative amount', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $account = Account::factory()->create();
    $category = Category::factory()->expense()->create();

    $this->post('/accounting/bookings', [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '3520.00',
        'booking_date' => '2026-04-01',
    ])->assertRedirect();

    expect(Booking::first()->amount_cents)->toBe(-352000);
});

it('defaults the valuta date to the booking date', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->post('/accounting/bookings', [
        'account_id' => Account::factory()->create()->id,
        'category_id' => Category::factory()->income()->create()->id,
        'amount' => '10',
        'booking_date' => '2026-04-05',
    ])->assertRedirect();

    expect(Booking::first()->valuta_date->toDateString())->toBe('2026-04-05');
});

it('prefers a linked user over a free-text counterparty', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $user = User::factory()->create();

    $this->post('/accounting/bookings', [
        'account_id' => Account::factory()->create()->id,
        'category_id' => Category::factory()->income()->create()->id,
        'amount' => '10',
        'booking_date' => '2026-04-05',
        'counterparty_user_id' => $user->id,
        'counterparty_name' => 'ignored',
    ])->assertRedirect();

    $booking = Booking::first();
    expect($booking->counterparty_user_id)->toBe($user->id)
        ->and($booking->counterparty_name)->toBeNull();
});

it('re-signs the amount when a booking is edited', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $booking = Booking::factory()->expense()->create(['amount_cents' => -1000]);
    $expense = Category::factory()->expense()->create();

    $this->put("/accounting/bookings/{$booking->id}", [
        'account_id' => $booking->account_id,
        'category_id' => $expense->id,
        'amount' => '42.50',
        'booking_date' => '2026-04-02',
    ])->assertRedirect();

    expect($booking->refresh()->amount_cents)->toBe(-4250);
});

it('filters bookings by account', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $a = Account::factory()->create();
    $b = Account::factory()->create();
    Booking::factory()->for($a)->create();
    Booking::factory()->for($b)->create();

    $this->get('/accounting/bookings?account='.$a->id)
        ->assertInertia(fn (AssertableInertia $page) => $page->has('bookings.data', 1));
});

it('filtering by a parent category includes its descendants', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $root = Category::factory()->expense()->create();
    $child = Category::factory()->childOf($root)->create();
    $grandchild = Category::factory()->childOf($child)->create();
    $other = Category::factory()->expense()->create();

    Booking::factory()->create(['category_id' => $root->id]);
    Booking::factory()->create(['category_id' => $child->id]);
    Booking::factory()->create(['category_id' => $grandchild->id]);
    Booking::factory()->create(['category_id' => $other->id]);

    // Root filter catches root + child + grandchild (3), not the unrelated one.
    $this->get('/accounting/bookings?category='.$root->id)
        ->assertInertia(fn (AssertableInertia $page) => $page->has('bookings.data', 3));
});

it('rejects a zero or negative amount', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->post('/accounting/bookings', [
        'account_id' => Account::factory()->create()->id,
        'category_id' => Category::factory()->income()->create()->id,
        'amount' => '0',
        'booking_date' => '2026-04-05',
    ])->assertSessionHasErrors('amount');
});

it('deletes a booking', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $booking = Booking::factory()->create();

    $this->delete("/accounting/bookings/{$booking->id}")->assertRedirect();
    expect(Booking::find($booking->id))->toBeNull();
});

it('can flip a booking back to draft from the edit form', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    $category = Category::factory()->income()->create();

    $this->put("/accounting/bookings/{$booking->id}", [
        'account_id' => $booking->account_id,
        'category_id' => $category->id,
        'amount' => '10',
        'booking_date' => '2026-04-02',
        'status' => 'draft',
    ])->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::Draft);
});

it('reviews AI-ready bookings oldest first, skipping un-analysed drafts', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $older = Booking::factory()->suggested()->create(['booking_date' => '2026-04-01']);
    Booking::factory()->suggested()->create(['booking_date' => '2026-04-10']);
    Booking::factory()->draft()->create(['booking_date' => '2026-03-01']); // not analysed → excluded

    $this->get('/accounting/bookings/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Bookings/Review')
            ->where('booking.id', $older->id)
            ->where('remaining', 2));
});

it('confirms the current draft with the full form and keeps the sign', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $draft = Booking::factory()->draft()->expense()->create(['amount_cents' => -1000, 'category_id' => null]);
    $category = Category::factory()->expense()->create();

    $this->patch("/accounting/bookings/{$draft->id}/review", [
        'action' => 'confirm',
        'account_id' => $draft->account_id,
        'category_id' => $category->id,
        'amount' => '35.20',
        'booking_date' => '2026-04-01',
        'comment' => 'Miete',
    ])->assertRedirect();

    $draft->refresh();
    expect($draft->status)->toBe(BookingStatus::Confirmed)
        ->and($draft->category_id)->toBe($category->id)
        ->and($draft->amount_cents)->toBe(-3520) // magnitude re-signed to expense
        ->and($draft->comment)->toBe('Miete');
});

it('rejects a review category with the wrong direction', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $draft = Booking::factory()->draft()->expense()->create(['amount_cents' => -1000, 'category_id' => null]);
    $income = Category::factory()->income()->create();

    $this->patch("/accounting/bookings/{$draft->id}/review", [
        'action' => 'confirm',
        'account_id' => $draft->account_id,
        'category_id' => $income->id,
        'amount' => '10',
        'booking_date' => '2026-04-01',
    ])->assertSessionHasErrors('category_id');

    expect($draft->refresh()->status)->toBe(BookingStatus::Draft);
});

it('discards a draft during review', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $draft = Booking::factory()->draft()->create();

    $this->patch("/accounting/bookings/{$draft->id}/review", ['action' => 'discard'])
        ->assertRedirect();

    expect(Booking::find($draft->id))->toBeNull();
});
