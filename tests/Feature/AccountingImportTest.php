<?php

declare(strict_types=1);

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function statementCsv(): string
{
    $utf8 = "Kontonummer;Buchungsdatum;Valuta;Verwendungszweck;Betrag;Waehrung\r\n"
        ."12345;01.04.2026;01.04.2026;SEPA-GUTSCHRIFT Essensgeld;50,00;EUR\r\n"
        ."12345;01.04.2026;31.03.2026;DAUERAUFTRAG Miete;-3.520,00;EUR\r\n";

    return mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
}

function uploadStatement(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('umsatz.csv', statementCsv());
}

it('forbids non-admins from the import page', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/accounting/import')->assertForbidden();
});

it('accepts a UTF-16 .csv but rejects other extensions with a friendly message', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    // A UTF-16 CSV is detected as octet-stream but has a .csv extension → accepted.
    $this->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()])
        ->assertSessionHasNoErrors();

    $this->post('/accounting/import', [
        'account_id' => $account->id,
        'file' => UploadedFile::fake()->create('statement.pdf', 10),
    ])->assertSessionHasErrors(['file' => __('accounting.import.file_invalid')]);
});

it('imports statement rows as draft bookings with the right sign', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();

    $this->actingAs($admin)
        ->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()])
        ->assertRedirect();

    $drafts = Booking::where('account_id', $account->id)->get();
    expect($drafts)->toHaveCount(2)
        ->and($drafts->every(fn (Booking $b) => $b->status === BookingStatus::Draft))->toBeTrue();

    $income = $drafts->firstWhere('amount_cents', 5000);
    $expense = $drafts->firstWhere('amount_cents', -352000);
    expect($income->kind)->toBe(BookingKind::Income)
        ->and($income->category_id)->toBeNull()
        ->and($expense->kind)->toBe(BookingKind::Expense);

    $import = Import::first();
    expect($import->imported_count)->toBe(2)->and($import->duplicate_count)->toBe(0);
});

it('skips duplicate rows on re-import', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    $this->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);
    $this->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);

    // Still only the first two — the second upload is all duplicates.
    expect(Booking::where('account_id', $account->id)->count())->toBe(2)
        ->and(Import::latest('id')->first()->duplicate_count)->toBe(2);
});

it('surfaces the skipped duplicate rows on the summary for confirmation', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    $this->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);
    $second = $this->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);

    $import = Import::latest('id')->first();
    expect($import->skipped_rows)->toHaveCount(2); // both rows were skipped as repeats

    $this->get("/accounting/import/{$import->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('skipped', 2));
});

it('imports a confirmed skipped row and removes it from the skipped list', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    $this->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);
    $this->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);
    $import = Import::latest('id')->first();

    // Confirm the first skipped row is genuine → it's imported.
    $this->post("/accounting/import/{$import->id}/confirm-skipped", ['rows' => [0]])
        ->assertRedirect();

    $import->refresh();
    expect(Booking::where('account_id', $account->id)->count())->toBe(3) // 2 + 1 confirmed
        ->and($import->skipped_rows)->toHaveCount(1)                     // one still pending
        ->and($import->imported_count)->toBe(1)
        ->and($import->duplicate_count)->toBe(1);
});

it('shows a post-upload summary with the draft total', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);
    $this->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);
    $import = Import::first();

    $this->get("/accounting/import/{$import->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Import/Summary')
            ->where('batch.imported_count', 2)
            ->where('draftTotal', 2));
});
