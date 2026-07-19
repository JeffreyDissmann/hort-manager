<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreImportRequest;
use App\Jobs\SuggestBookingCategory;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Import;
use App\Support\Accounting\BankStatementParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** Admin-only bank-statement CSV import (upload + post-upload summary). */
class ImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Accounting/Import/Create', [
            'accounts' => Account::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreImportRequest $request, BankStatementParser $parser): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        $rows = $parser->parse($request->file('file')->get());

        // Existing hashes on this account (so re-uploading overlapping months skips repeats).
        $existing = Booking::where('account_id', $account->id)
            ->whereNotNull('import_hash')
            ->pluck('import_hash')
            ->flip();

        $import = Import::create([
            'account_id' => $account->id,
            'uploaded_by' => Auth::id(),
            'filename' => $request->file('file')->getClientOriginalName(),
            'row_count' => count($rows),
        ]);

        $duplicates = 0;
        $seen = [];
        $drafts = collect();

        foreach ($rows as $row) {
            $hash = $this->hashFor($account->id, $row);

            if (isset($existing[$hash]) || isset($seen[$hash])) {
                $duplicates++;

                continue;
            }
            $seen[$hash] = true;

            $drafts->push(Booking::create([
                'account_id' => $account->id,
                'import_id' => $import->id,
                'category_id' => null,
                'kind' => $row['amount_cents'] >= 0 ? BookingKind::Income : BookingKind::Expense,
                'status' => BookingStatus::Draft,
                'amount_cents' => $row['amount_cents'],
                'currency' => $row['currency'],
                'booking_date' => $row['booking_date'],
                'valuta_date' => $row['valuta_date'],
                'purpose' => $row['purpose'],
                'import_hash' => $hash,
            ]));
        }

        $import->update(['imported_count' => $drafts->count(), 'duplicate_count' => $duplicates]);

        // Queue the AI pass: one job per draft, globally serialized (one Ollama call
        // at a time). Drafts flip to "suggested" as each job completes. Disabled in
        // tests / when Ollama is off.
        if (config('accounting.ai_suggestions')) {
            $drafts->each(fn (Booking $b) => SuggestBookingCategory::dispatch($b->id));
        }

        return redirect()->route('accounting.import.show', $import);
    }

    /** Post-upload summary: what was imported, skipped, and how much awaits review. */
    public function show(Import $import): Response
    {
        return Inertia::render('Accounting/Import/Summary', [
            'batch' => [
                'account' => $import->account?->name,
                'filename' => $import->filename,
                'imported_count' => $import->imported_count,
                'duplicate_count' => $import->duplicate_count,
            ],
            // Total bookings awaiting review across the whole ledger (not just this file).
            'draftTotal' => Booking::needsReview()->count(),
        ]);
    }

    /**
     * @param  array{booking_date:string, valuta_date:string, purpose:string, amount_cents:int, currency:string}  $row
     */
    private function hashFor(int $accountId, array $row): string
    {
        return hash('sha256', implode('|', [
            $accountId,
            $row['booking_date'],
            $row['valuta_date'],
            $row['purpose'],
            $row['amount_cents'],
        ]));
    }
}
