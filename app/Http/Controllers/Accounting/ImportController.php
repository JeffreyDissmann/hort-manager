<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreImportMappingRequest;
use App\Http\Requests\Accounting\StoreImportRequest;
use App\Jobs\SuggestBookingCategory;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Import;
use App\Support\Accounting\CsvReader;
use App\Support\Accounting\StatementMapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only bank-statement CSV import. Three steps: upload → confirm the
 * auto-guessed column mapping → post-upload summary. The upload only decodes the
 * file and stores its raw columns; drafts are created once the mapping is confirmed.
 */
class ImportController extends Controller
{
    /** How many data rows to preview on the mapping screen. */
    private const PREVIEW_ROWS = 6;

    public function create(): Response
    {
        return Inertia::render('Accounting/Import/Create', [
            'accounts' => Account::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Decode the upload into a raw table and stash it against a pending import, then
     * send the user to confirm the guessed column mapping. No drafts are created yet.
     */
    public function store(StoreImportRequest $request, CsvReader $reader): RedirectResponse
    {
        $account = Account::findOrFail($request->integer('account_id'));
        $table = $reader->read($request->file('file')->get());

        if ($table['header'] === [] || $table['rows'] === []) {
            return back()->withErrors(['file' => __('accounting.import.file_empty')]);
        }

        $import = Import::create([
            'account_id' => $account->id,
            'uploaded_by' => Auth::id(),
            'filename' => $request->file('file')->getClientOriginalName(),
            'row_count' => count($table['rows']),
            'pending_columns' => $table,
        ]);

        return redirect()->route('accounting.import.configure', $import);
    }

    /** Confirm/adjust the column mapping for a pending import. */
    public function configure(Import $import, StatementMapper $mapper): Response|RedirectResponse
    {
        if ($import->isMapped()) {
            return redirect()->route('accounting.import.show', $import);
        }

        $table = $import->pending_columns;

        return Inertia::render('Accounting/Import/Configure', [
            'batch' => ['id' => $import->id, 'filename' => $import->filename, 'account' => $import->account?->name],
            'header' => $table['header'],
            'preview' => array_slice($table['rows'], 0, self::PREVIEW_ROWS),
            'rowCount' => count($table['rows']),
            'mapping' => $mapper->guess($table['header']),
            'fields' => StatementMapper::FIELDS,
            'requiredFields' => StatementMapper::REQUIRED_FIELDS,
        ]);
    }

    /**
     * Apply the confirmed mapping: normalize the stashed rows and create draft
     * bookings (skipping duplicates), then finalize the import.
     */
    public function storeMapping(StoreImportMappingRequest $request, Import $import, StatementMapper $mapper): RedirectResponse
    {
        if ($import->isMapped()) {
            return redirect()->route('accounting.import.show', $import);
        }

        $mapping = $request->mapping();
        $rows = $mapper->normalize($import->pending_columns['rows'] ?? [], $mapping);

        $account = $import->account;

        // Existing hashes on this account (so re-uploading overlapping months skips repeats).
        $existing = Booking::where('account_id', $account->id)
            ->whereNotNull('import_hash')
            ->pluck('import_hash')
            ->flip();

        $seen = [];
        $skipped = [];
        $drafts = collect();

        foreach ($rows as $row) {
            $hash = $this->hashFor($account->id, $row);

            // Looks like a repeat — keep the row so the user can decide it's genuine.
            if (isset($existing[$hash]) || isset($seen[$hash])) {
                $skipped[] = $row;

                continue;
            }
            $seen[$hash] = true;

            $drafts->push($this->createDraft($account, $import, $row, $hash));
        }

        $import->update([
            'column_mapping' => $mapping,
            'pending_columns' => null,
            'row_count' => count($rows),
            'imported_count' => $drafts->count(),
            'duplicate_count' => count($skipped),
            'skipped_rows' => $skipped,
        ]);

        $this->queueSuggestions($drafts);

        return redirect()->route('accounting.import.show', $import);
    }

    /**
     * Import the duplicate rows the user has confirmed are genuine (by their index in
     * the import's skipped list), removing them from that list.
     */
    public function confirmSkipped(Request $request, Import $import): RedirectResponse
    {
        $skipped = $import->skipped_rows ?? [];

        $selected = collect($request->validate([
            'rows' => ['array'],
            'rows.*' => ['integer'],
        ])['rows'] ?? [])->unique()->filter(fn (int $i): bool => isset($skipped[$i]));

        if ($selected->isEmpty()) {
            return back();
        }

        $account = $import->account;
        $created = $selected->map(fn (int $i): Booking => $this->createDraft($account, $import, $skipped[$i]));

        $import->update([
            'skipped_rows' => collect($skipped)->except($selected->all())->values()->all(),
            'imported_count' => $import->imported_count + $created->count(),
            'duplicate_count' => max(0, $import->duplicate_count - $created->count()),
        ]);

        $this->queueSuggestions($created);

        return back()->with('status', __('flash.import_skipped_added', ['count' => $created->count()]));
    }

    /**
     * Create one draft booking from a parsed statement row.
     *
     * @param  array{booking_date:string, valuta_date:string, purpose:string, amount_cents:int, currency:string}  $row
     */
    private function createDraft(Account $account, Import $import, array $row, ?string $hash = null): Booking
    {
        return Booking::create([
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
            'import_hash' => $hash ?? $this->hashFor($account->id, $row),
        ]);
    }

    /**
     * Queue the AI pass: one job per draft, globally serialized (one Ollama call at a
     * time). Drafts flip to "suggested" as each completes. Off in tests / when Ollama is off.
     *
     * @param  Collection<int, Booking>  $drafts
     */
    private function queueSuggestions(Collection $drafts): void
    {
        if (config('accounting.ai_suggestions')) {
            $drafts->each(fn (Booking $b) => SuggestBookingCategory::dispatch($b->id));
        }
    }

    /** Post-upload summary: what was imported, skipped, and AI-analysis progress. */
    public function show(Import $import): Response|RedirectResponse
    {
        if (! $import->isMapped()) {
            return redirect()->route('accounting.import.configure', $import);
        }

        return Inertia::render('Accounting/Import/Summary', [
            'batch' => [
                'id' => $import->id,
                'account' => $import->account?->name,
                'filename' => $import->filename,
                'imported_count' => $import->imported_count,
                'duplicate_count' => $import->duplicate_count,
            ],
            // The skipped duplicate rows, surfaced so genuine ones can be imported.
            'skipped' => collect($import->skipped_rows ?? [])
                ->map(fn (array $row, int $i): array => [
                    'index' => $i,
                    'booking_date' => $row['booking_date'],
                    'purpose' => $row['purpose'],
                    'amount_cents' => $row['amount_cents'],
                ])
                ->values(),
            // Total bookings awaiting review across the whole ledger (not just this file).
            'draftTotal' => Booking::needsReview()->count(),
            // Live AI progress for this import (polled by the summary page).
            'progress' => [
                'total' => $import->imported_count,
                'analyzed' => $import->bookings()->where('status', BookingStatus::Suggested)->count(),
                'pending' => $import->bookings()->where('status', BookingStatus::Draft)->count(),
            ],
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
