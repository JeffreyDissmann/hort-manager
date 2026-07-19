<?php

declare(strict_types=1);

use App\Ai\Agents\BookingCategorizer;
use App\Enums\BookingStatus;
use App\Jobs\SuggestBookingCategory;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\Child;
use App\Models\User;
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
    $account = Account::factory()->create();
    $income = Category::factory()->income()->create();
    $emma = Child::factory()->create(['name' => 'Emma']);

    BookingCategorizer::fake([
        ['category_id' => $income->id, 'counterparty_child_id' => $emma->id],
        [],
    ]);

    $this->actingAs($admin)
        ->post('/accounting/import', ['account_id' => $account->id, 'file' => suggestionCsv()]);

    $this->actingAs($admin)
        ->get('/accounting/bookings/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Bookings/Review')
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
