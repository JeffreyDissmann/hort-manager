<?php

declare(strict_types=1);

use App\Ai\Agents\BookingCategorizer;
use App\Enums\BookingStatus;
use App\Enums\SuggestionConfidence;
use App\Jobs\SuggestBookingCategory;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\Child;
use App\Models\User;
use App\Services\Accounting\BookingSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function suggestionCsv(): UploadedFile
{
    $utf8 = "Kontonummer;Buchungsdatum;Valuta;Verwendungszweck;Betrag;Waehrung\r\n"
        ."12345;01.04.2026;01.04.2026;SEPA-GUTSCHRIFT Essensgeld Emma;50,00;EUR\r\n"
        ."12345;01.04.2026;31.03.2026;DAUERAUFTRAG Miete;-3.520,00;EUR\r\n";

    return UploadedFile::fake()->createWithContent('umsatz.csv', mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8'));
}

beforeEach(function () {
    config(['accounting.ai_suggestions' => true]);
});

it('moves imported drafts to suggested and stores AI suggestions', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $income = Category::factory()->income()->create();
    $expense = Category::factory()->expense()->create();
    $emma = Child::factory()->create(['name' => 'Emma']);

    // One call per booking, responses consumed in dispatch (CSV) order:
    // row 0 = income (Essensgeld), row 1 = expense (Miete).
    BookingCategorizer::fake([
        ['category_id' => $income->id, 'counterparty_child_id' => $emma->id],
        ['category_id' => $expense->id],
    ]);

    $this->actingAs($admin)
        ->post('/accounting/import', ['account_id' => $account->id, 'file' => suggestionCsv()])
        ->assertRedirect();

    $incomeRow = Booking::where('amount_cents', 5000)->first();
    $expenseRow = Booking::where('amount_cents', -352000)->first();

    expect($incomeRow->status)->toBe(BookingStatus::Suggested)
        ->and($incomeRow->category_id)->toBe($income->id)
        ->and($incomeRow->counterparty_child_id)->toBe($emma->id)
        ->and($expenseRow->status)->toBe(BookingStatus::Suggested)
        ->and($expenseRow->category_id)->toBe($expense->id);
});

it('drops a suggested category whose direction is wrong', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $income = Category::factory()->income()->create();

    // Row 1 (Miete, an expense) is offered an income category — must be dropped.
    BookingCategorizer::fake([
        [],
        ['category_id' => $income->id],
    ]);

    $this->actingAs($admin)
        ->post('/accounting/import', ['account_id' => $account->id, 'file' => suggestionCsv()]);

    expect(Booking::where('amount_cents', -352000)->first()->category_id)->toBeNull();
});

it('pre-fills the review form from the AI suggestion', function () {
    $admin = User::factory()->admin()->create();
    $income = Category::factory()->income()->create();
    $emma = Child::factory()->create(['name' => 'Emma']);
    $booking = Booking::factory()->suggested()->create([
        'category_id' => $income->id,
        'counterparty_child_id' => $emma->id,
        'confidence' => SuggestionConfidence::High,
    ]);

    $this->actingAs($admin)
        ->get('/accounting/bookings/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Bookings/Review')
            ->where('booking.id', $booking->id)
            ->where('booking.category_id', $income->id)
            ->where('booking.counterparty_child_id', $emma->id)
            ->where('booking.ai_suggested', true));
});

it('queues one suggestion job per imported draft', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();

    $this->actingAs($admin)
        ->post('/accounting/import', ['account_id' => $account->id, 'file' => suggestionCsv()]);

    Queue::assertPushed(SuggestBookingCategory::class, 2);
});

it('re-analyses unconfirmed bookings and leaves confirmed ones alone', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $suggested = Booking::factory()->suggested()->create();
    $confirmed = Booking::factory()->create(); // factory default = confirmed

    $this->actingAs($admin)->post('/accounting/bookings/reanalyse')->assertRedirect();

    expect($suggested->refresh()->status)->toBe(BookingStatus::Draft)
        ->and($confirmed->refresh()->status)->toBe(BookingStatus::Confirmed);
    Queue::assertPushed(SuggestBookingCategory::class, 1); // only the unconfirmed one
});

it('trusts the model: high confidence is taken at face value', function () {
    $this->actingAs(User::factory()->admin()->create());
    $expense = Category::factory()->expense()->create(); // random name, not in the purpose
    $booking = Booking::factory()->draft()->create(['amount_cents' => -6842, 'category_id' => null, 'purpose' => 'EC-POS REWE SAGT DANKE']);

    BookingCategorizer::fake([['category_id' => $expense->id, 'counterparty_name' => 'REWE', 'confidence' => 'high']]);
    app(BookingSuggester::class)->suggest($booking->fresh());

    expect($booking->refresh()->confidence)->toBe(SuggestionConfidence::High);
});

it('trusts the model: medium stays medium', function () {
    $this->actingAs(User::factory()->admin()->create());
    $expense = Category::factory()->expense()->create();
    $booking = Booking::factory()->draft()->create(['amount_cents' => -3520, 'category_id' => null, 'purpose' => 'DAUERAUFTRAG Miete']);

    BookingCategorizer::fake([['category_id' => $expense->id, 'counterparty_name' => 'Vermietung', 'confidence' => 'medium']]);
    app(BookingSuggester::class)->suggest($booking->fresh());

    expect($booking->refresh()->confidence)->toBe(SuggestionConfidence::Medium);
});

it('overrides the model to low when the AI found no category (even if it claims high)', function () {
    $this->actingAs(User::factory()->admin()->create());
    $booking = Booking::factory()->draft()->create(['amount_cents' => -890, 'category_id' => null, 'purpose' => 'Kontoführung']);

    BookingCategorizer::fake([['category_id' => null, 'confidence' => 'high']]);
    app(BookingSuggester::class)->suggest($booking->fresh());

    expect($booking->refresh()->confidence)->toBe(SuggestionConfidence::Low);
});

it('keeps the model category but flags low when a different category is named in the purpose', function () {
    $this->actingAs(User::factory()->admin()->create());
    $elternbeitrag = Category::factory()->income()->create(['name' => 'Elternbeitrag']);
    Category::factory()->income()->create(['name' => 'Vereinsbeitrag']);

    // Text clearly says „Vereinsbeitrag" but the model wrongly picked Elternbeitrag.
    $booking = Booking::factory()->draft()->create(['amount_cents' => 11000, 'category_id' => null, 'purpose' => 'SEPA-DAUERAUFTRAG Vereinsbeitrag Nora']);
    BookingCategorizer::fake([['category_id' => $elternbeitrag->id, 'confidence' => 'high']]);
    app(BookingSuggester::class)->suggest($booking->fresh());

    // The model's pick is untouched; the conflict just drops confidence to low for review.
    expect($booking->refresh()->category_id)->toBe($elternbeitrag->id)
        ->and($booking->confidence)->toBe(SuggestionConfidence::Low);
});

it('is confident when the chosen category is literally named in the purpose', function () {
    $this->actingAs(User::factory()->admin()->create());
    $vereinsbeitrag = Category::factory()->income()->create(['name' => 'Vereinsbeitrag']);

    // The model's own pick matches the literally-named category → certain, despite „medium".
    $booking = Booking::factory()->draft()->create(['amount_cents' => 11000, 'category_id' => null, 'purpose' => 'SEPA-DAUERAUFTRAG Vereinsbeitrag Nora']);
    BookingCategorizer::fake([['category_id' => $vereinsbeitrag->id, 'confidence' => 'medium']]);
    app(BookingSuggester::class)->suggest($booking->fresh());

    expect($booking->refresh()->category_id)->toBe($vereinsbeitrag->id)
        ->and($booking->confidence)->toBe(SuggestionConfidence::High);
});

it('downgrades to low when two categories are named and the model picks a third', function () {
    $this->actingAs(User::factory()->admin()->create());
    Category::factory()->income()->create(['name' => 'Vereinsbeitrag']);
    Category::factory()->income()->create(['name' => 'Essensgeld']);
    $kaution = Category::factory()->income()->create(['name' => 'Kaution']);

    // Two category names in the text → no override; the model picks an unrelated third.
    $booking = Booking::factory()->draft()->create(['amount_cents' => 11000, 'category_id' => null, 'purpose' => 'Vereinsbeitrag und Essensgeld Sammelzahlung']);
    BookingCategorizer::fake([['category_id' => $kaution->id, 'confidence' => 'high']]);
    app(BookingSuggester::class)->suggest($booking->fresh());

    expect($booking->refresh()->confidence)->toBe(SuggestionConfidence::Low);
});

it('reviews riskiest (lowest confidence) first', function () {
    $this->actingAs(User::factory()->admin()->create());
    Booking::factory()->suggested()->create(['confidence' => SuggestionConfidence::High, 'booking_date' => '2026-04-01']);
    $risky = Booking::factory()->suggested()->create(['confidence' => SuggestionConfidence::Low, 'booking_date' => '2026-04-20']);

    $this->get('/accounting/bookings/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('booking.id', $risky->id) // low confidence first, despite the later date
            ->where('booking.confidence', SuggestionConfidence::Low->value));
});

it('never overwrites a booking confirmed while the AI was running', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $keep = Category::factory()->expense()->create();
    $other = Category::factory()->expense()->create();
    $booking = Booking::factory()->draft()->create(['amount_cents' => -1000, 'category_id' => null]);

    // The job captured the booking while it was a draft…
    $stale = $booking->fresh();
    // …but a reviewer confirmed it (with a category + counterparty) before the AI returned.
    $booking->update([
        'status' => BookingStatus::Confirmed,
        'category_id' => $keep->id,
        'counterparty_name' => 'Kept Co',
        'confidence' => SuggestionConfidence::High,
    ]);

    // The stale AI result tries to write a different category AND counterparty/confidence.
    $emma = Child::factory()->create();
    BookingCategorizer::fake([['category_id' => $other->id, 'counterparty_child_id' => $emma->id, 'counterparty_name' => 'Stale', 'confidence' => 'low']]);
    app(BookingSuggester::class)->suggest($stale);

    // Nothing the conditional update writes may touch the confirmed booking.
    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->category_id)->toBe($keep->id)
        ->and($booking->counterparty_name)->toBe('Kept Co')
        ->and($booking->counterparty_child_id)->toBeNull()
        ->and($booking->confidence)->toBe(SuggestionConfidence::High);
});
