<?php

declare(strict_types=1);

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\SuggestionConfidence;
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

it('exposes the pending-draft count and AI flag to drive list polling', function () {
    config(['accounting.ai_suggestions' => true]);
    $admin = User::factory()->admin()->create();
    Booking::factory()->draft()->create();
    Booking::factory()->draft()->create();
    Booking::factory()->suggested()->create();
    Booking::factory()->create(); // confirmed

    $this->actingAs($admin)
        ->get('/accounting/bookings')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pendingCount', 2) // only the un-analysed drafts
            ->where('aiEnabled', true));
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

it('filters suggested bookings by confidence via the status filter', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Booking::factory()->suggested()->create(['confidence' => SuggestionConfidence::Low]);
    Booking::factory()->suggested()->create(['confidence' => SuggestionConfidence::High]);
    Booking::factory()->create(); // confirmed

    // Plain status still works…
    $this->get('/accounting/bookings?status=suggested')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('bookings.data', 2));

    // …and the composite „suggested:0" (low) narrows to the risky one.
    $this->get('/accounting/bookings?status=suggested%3A0')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('bookings.data', 1)
            ->where('bookings.data.0.confidence', SuggestionConfidence::Low->value));
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

it('bulk-confirms the given ids, skipping uncategorised bookings', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $withCat = Booking::factory()->suggested()->create();                       // has a category
    $noCat = Booking::factory()->suggested()->create(['category_id' => null]);   // no category

    $this->post('/accounting/bookings/confirm', ['ids' => [$withCat->id, $noCat->id]])
        ->assertRedirect();

    expect($withCat->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($noCat->refresh()->status)->toBe(BookingStatus::Suggested); // untouched (no category)
});

it('bulk-confirms all unconfirmed bookings matching the filter', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Booking::factory()->count(3)->suggested()->create();
    $alreadyConfirmed = Booking::factory()->create();

    $this->post('/accounting/bookings/confirm', ['all' => true, 'filters' => []])
        ->assertRedirect();

    expect(Booking::where('status', BookingStatus::Confirmed)->count())->toBe(4)
        ->and($alreadyConfirmed->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('bulk-confirming all honours the active filter, leaving non-matching bookings alone', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();
    $a = Booking::factory()->suggested()->create(['account_id' => $accountA->id]);
    $b = Booking::factory()->suggested()->create(['account_id' => $accountB->id]);

    $this->post('/accounting/bookings/confirm', ['all' => true, 'filters' => ['account' => $accountA->id]])
        ->assertRedirect();

    expect($a->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($b->refresh()->status)->toBe(BookingStatus::Suggested);
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

it('skips a draft, advancing the cursor to the next by (confidence, id)', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $first = Booking::factory()->suggested()->create(['confidence' => SuggestionConfidence::Low]);
    $next = Booking::factory()->suggested()->create(['confidence' => SuggestionConfidence::Low]);

    // Review serves the lowest (confidence, id) first; skipping advances to the next…
    $this->patch("/accounting/bookings/{$first->id}/review", ['action' => 'skip'])
        ->assertRedirect(route('accounting.bookings.review', ['cursor' => $next->id]));

    // …and leaves both bookings untouched.
    expect($first->refresh()->status)->toBe(BookingStatus::Suggested)
        ->and($next->refresh()->status)->toBe(BookingStatus::Suggested);
});

it('refuses to review an already-confirmed booking', function () {
    $admin = User::factory()->admin()->create();
    $confirmed = Booking::factory()->create(); // factory default = confirmed

    $this->actingAs($admin)
        ->patch("/accounting/bookings/{$confirmed->id}/review", ['action' => 'confirm'])
        ->assertNotFound();
});

it('filters by kind, confirmed status and a month range (report drill-down)', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $income = Category::factory()->income()->create();
    $expense = Category::factory()->expense()->create();

    Booking::factory()->create(['category_id' => $income->id, 'booking_date' => '2026-01-10']);   // confirmed income, Jan
    Booking::factory()->create(['category_id' => $income->id, 'booking_date' => '2026-02-10']);   // confirmed income, Feb
    Booking::factory()->expense()->create(['category_id' => $expense->id, 'booking_date' => '2026-01-15']); // confirmed expense, Jan
    Booking::factory()->suggested()->create(['category_id' => $income->id, 'booking_date' => '2026-01-20']); // unconfirmed, Jan

    // "Einnahmen · Januar" drill-down → only the one confirmed income booking.
    $this->get('/accounting/bookings?kind=income&status=confirmed&from=2026-01-01&to=2026-01-31')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('bookings.data', 1));
});

it('forbids non-admins from exporting the bookings list', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/accounting/bookings/export')
        ->assertForbidden();
});

it('exports every matching booking, not just the current page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Booking::factory()->count(60)->create(); // the list paginates at 50

    $response = $this->get('/accounting/bookings/export?format=csv')->assertOk()->assertDownload('bookings.csv');

    $lines = array_filter(explode("\n", trim(file_get_contents($response->baseResponse->getFile()->getPathname()))));
    expect($lines)->toHaveCount(61); // header + all 60 rows
});

it('exports only the bookings matching the active filter', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $a = Account::factory()->create();
    $b = Account::factory()->create();
    Booking::factory()->count(3)->for($a)->create();
    Booking::factory()->count(2)->for($b)->create();

    $response = $this->get('/accounting/bookings/export?format=csv&account='.$a->id)->assertOk();

    $lines = array_filter(explode("\n", trim(file_get_contents($response->baseResponse->getFile()->getPathname()))));
    expect($lines)->toHaveCount(4); // header + 3 for account A only
});

it('exports the bookings list as an XLSX download', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->get('/accounting/bookings/export?format=xlsx')
        ->assertOk()
        ->assertDownload('bookings.xlsx');
});
