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

/** The column mapping for the standard Kontonummer;Buchungsdatum;… layout. */
function standardMapping(): array
{
    return ['booking_date' => 1, 'valuta_date' => 2, 'purpose' => 3, 'amount' => 4, 'currency' => 5];
}

/** Run the two-step import (upload → confirm mapping) and return the finished Import. */
function importStatement(Account $account, ?UploadedFile $file = null, ?array $mapping = null): Import
{
    test()->post('/accounting/import', ['account_id' => $account->id, 'file' => $file ?? uploadStatement()]);
    $import = Import::latest('id')->first();
    test()->post("/accounting/import/{$import->id}/configure", ['mapping' => $mapping ?? standardMapping()]);

    return $import->refresh();
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

it('parks the upload as a pending import and sends the user to map the columns', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();

    $this->actingAs($admin)
        ->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()])
        ->assertRedirect();

    $import = Import::latest('id')->first();
    // No drafts yet — the file is only decoded and stashed.
    expect(Booking::count())->toBe(0)
        ->and($import->isMapped())->toBeFalse()
        ->and($import->pending_columns['header'])->toContain('Buchungsdatum');
});

it('shows the mapping screen with a guessed mapping and a preview', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin)->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);
    $import = Import::latest('id')->first();

    $this->get("/accounting/import/{$import->id}/configure")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Import/Configure')
            ->where('mapping.booking_date', 1)
            ->where('mapping.amount', 4)
            ->where('mapping.currency', 5)
            ->where('rowCount', 2)
            ->has('preview', 2)
            ->has('header', 6));
});

it('imports statement rows as draft bookings with the right sign once the mapping is confirmed', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    $import = importStatement($account);

    $drafts = Booking::where('account_id', $account->id)->get();
    expect($drafts)->toHaveCount(2)
        ->and($drafts->every(fn (Booking $b) => $b->status === BookingStatus::Draft))->toBeTrue();

    $income = $drafts->firstWhere('amount_cents', 5000);
    $expense = $drafts->firstWhere('amount_cents', -352000);
    expect($income->kind)->toBe(BookingKind::Income)
        ->and($income->category_id)->toBeNull()
        ->and($expense->kind)->toBe(BookingKind::Expense);

    expect($import->imported_count)->toBe(2)
        ->and($import->duplicate_count)->toBe(0)
        ->and($import->isMapped())->toBeTrue()
        ->and($import->pending_columns)->toBeNull(); // cleared after mapping
});

it('respects an adjusted mapping', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    // Point „amount" at the Kontonummer column (index 0) instead of Betrag.
    importStatement($account, mapping: [...standardMapping(), 'amount' => 0]);

    // Kontonummer 12345 → 1234500 cents on every row.
    expect(Booking::pluck('amount_cents')->all())->toEqual([1234500, 1234500]);
});

it('rejects a mapping missing a required column', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin)->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);
    $import = Import::latest('id')->first();

    $this->post("/accounting/import/{$import->id}/configure", [
        'mapping' => ['booking_date' => 1, 'valuta_date' => 2, 'currency' => 5], // no purpose / amount
    ])->assertSessionHasErrors(['mapping.purpose', 'mapping.amount']);

    expect(Booking::count())->toBe(0);
});

it('skips duplicate rows on re-import', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    importStatement($account);
    importStatement($account);

    // Still only the first two — the second upload is all duplicates.
    expect(Booking::where('account_id', $account->id)->count())->toBe(2)
        ->and(Import::latest('id')->first()->duplicate_count)->toBe(2);
});

it('surfaces the skipped duplicate rows on the summary for confirmation', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    importStatement($account);
    importStatement($account);

    $import = Import::latest('id')->first();
    expect($import->skipped_rows)->toHaveCount(2); // both rows were skipped as repeats

    $this->get("/accounting/import/{$import->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('skipped', 2));
});

it('imports a confirmed skipped row and removes it from the skipped list', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin);

    importStatement($account);
    importStatement($account);
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
    $import = importStatement($account);

    $this->get("/accounting/import/{$import->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Import/Summary')
            ->where('batch.imported_count', 2)
            ->where('draftTotal', 2));
});

it('redirects the summary of a still-unmapped import back to the mapping step', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create();
    $this->actingAs($admin)->post('/accounting/import', ['account_id' => $account->id, 'file' => uploadStatement()]);
    $import = Import::latest('id')->first();

    $this->get("/accounting/import/{$import->id}")
        ->assertRedirect("/accounting/import/{$import->id}/configure");
});
