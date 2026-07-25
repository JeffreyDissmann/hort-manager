<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Support\Accounting\CategoryOptions;
use App\Support\Accounting\SpreadsheetExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin-only „Auswertung": a month × category pivot of the confirmed ledger for a
 * chosen year. Income and expense are shown as separate blocks (categories rolled
 * up over their subtree) with per-month and per-category totals and a monthly net.
 * Transfers are internal moves: they're shown in a separate „Umbuchungen" block,
 * broken down per account (each account's signed movement), whose total nets to zero
 * so it never distorts the income/expense net.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $years = $this->availableYears();
        $year = $request->integer('year') ?: (int) $years->first();

        $accounts = Account::orderBy('name')->get(['id', 'name']);
        $accountIds = $this->selectedAccountIds($request, $accounts);

        return Inertia::render('Accounting/Reports/Index', [
            'year' => $year,
            'years' => $years,
            // Which accounts feed the summary (all by default) + the pickable list.
            'accounts' => $accounts,
            'selectedAccounts' => $accountIds,
            ...$this->data($year, $accountIds),
        ]);
    }

    /** Download the year's pivot as CSV (German ;-delimited) or XLSX. */
    public function export(Request $request): BinaryFileResponse
    {
        $year = $request->integer('year') ?: (int) $this->availableYears()->first();
        $xlsx = strtolower((string) $request->string('format')) === 'xlsx';

        $accounts = Account::orderBy('name')->get(['id', 'name']);
        $accountIds = $this->selectedAccountIds($request, $accounts);

        return SpreadsheetExport::download($this->matrix($this->data($year, $accountIds)), "report-{$year}", $xlsx);
    }

    /**
     * The account ids the summary is scoped to. Absent or empty → all accounts (the
     * default), so a summary always covers something; otherwise the chosen subset.
     *
     * @param  Collection<int, Account>  $accounts
     * @return list<int>
     */
    private function selectedAccountIds(Request $request, Collection $accounts): array
    {
        $all = $accounts->pluck('id')->all();
        $requested = collect($request->input('accounts', []))
            ->map(fn ($id): int => (int) $id)
            ->intersect($all)
            ->values();

        return $requested->isEmpty() ? $all : $requested->all();
    }

    /**
     * The full month × category pivot for a year, scoped to the given accounts,
     * shared by the view and the export.
     *
     * @param  list<int>  $accountIds
     * @return array<string, mixed>
     */
    private function data(int $year, array $accountIds): array
    {
        // Confirmed, categorised, non-transfer bookings for the year (a Hort's ledger
        // is small enough to bucket per category/month in PHP — no DB-specific SQL).
        $bookings = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->where('kind', '!=', BookingKind::Transfer)
            ->whereNotNull('category_id')
            ->whereIn('account_id', $accountIds)
            ->whereYear('booking_date', $year)
            ->get(['category_id', 'amount_cents', 'booking_date']);

        $flat = collect(CategoryOptions::flat(onlyActive: false));
        $direction = $flat->pluck('direction', 'id');
        $parent = $flat->pluck('parent_id', 'id');

        // Bucket each booking into its month, rolling the amount up into its category
        // and every ancestor so a parent row reflects its whole subtree.
        $totals = [];
        $incomeMonths = array_fill(1, 12, 0);
        $expenseMonths = array_fill(1, 12, 0);

        foreach ($bookings as $booking) {
            $month = $booking->booking_date->month;
            $cents = $booking->amount_cents;

            for ($node = $booking->category_id; $node !== null; $node = $parent[$node] ?? null) {
                $totals[$node][$month] = ($totals[$node][$month] ?? 0) + $cents;
            }

            if (($direction[$booking->category_id] ?? null) === CategoryDirection::Income->value) {
                $incomeMonths[$month] += $cents;
            } elseif (($direction[$booking->category_id] ?? null) === CategoryDirection::Expense->value) {
                $expenseMonths[$month] += $cents;
            }
        }

        $netMonths = collect(range(1, 12))->map(fn (int $m): int => $incomeMonths[$m] + $expenseMonths[$m])->all();

        // Transfers: a per-account breakdown of the internal moves. Each account shows
        // its own signed movement (money out −, money in +); across all accounts the
        // months net to zero, so the block is a neutral memo below the P&L. When the
        // summary is scoped to a subset it shows just those accounts' movements.
        [$transferRows, $transferMonths] = $this->transfers($year, $accountIds);

        return [
            'monthLabels' => collect(range(1, 12))
                ->map(fn (int $m): string => CarbonImmutable::create($year, $m, 1)->translatedFormat('M'))->all(),
            'incomeRows' => $this->rows($flat, $totals, CategoryDirection::Income),
            'expenseRows' => $this->rows($flat, $totals, CategoryDirection::Expense),
            'incomeMonths' => array_values($incomeMonths),
            'expenseMonths' => array_values($expenseMonths),
            'netMonths' => $netMonths,
            'incomeTotal' => array_sum($incomeMonths),
            'expenseTotal' => array_sum($expenseMonths),
            'netTotal' => array_sum($netMonths),
            'transferRows' => $transferRows,
            'transferMonths' => array_values($transferMonths),
            'transferTotal' => array_sum($transferMonths),
        ];
    }

    /**
     * Flatten the pivot into tagged spreadsheet rows: a header, then the Einnahmen
     * block, the Ausgaben block, and the Saldo row. Amounts are euros (cents/100).
     * The `type` drives XLSX styling (head / total emphasis vs. plain row).
     *
     * @param  array<string, mixed>  $data
     * @return list<array{type:string, cells:list<string|float>}>
     */
    private function matrix(array $data): array
    {
        $euros = fn (int $cents): float => round($cents / 100, 2);
        $amountRow = fn (string $label, array $months, int $total): array => [
            $label,
            ...array_map($euros, $months),
            $euros($total),
        ];

        $rows = [['type' => 'head', 'cells' => [__('accounting.reports.category'), ...$data['monthLabels'], __('accounting.reports.total')]]];

        foreach ([
            [__('accounting.reports.income'), $data['incomeRows'], $data['incomeMonths'], $data['incomeTotal']],
            [__('accounting.reports.expense'), $data['expenseRows'], $data['expenseMonths'], $data['expenseTotal']],
        ] as [$label, $categoryRows, $months, $total]) {
            $rows[] = ['type' => 'total', 'cells' => $amountRow($label, $months, $total)];
            foreach ($categoryRows as $row) {
                $rows[] = ['type' => 'row', 'cells' => $amountRow(str_repeat('  ', $row['depth']).$row['name'], $row['months'], $row['total'])];
            }
        }

        // Umbuchungen — a per-account, zero-sum memo block.
        if ($data['transferRows'] !== []) {
            $rows[] = ['type' => 'total', 'cells' => $amountRow(__('accounting.reports.transfers'), $data['transferMonths'], $data['transferTotal'])];
            foreach ($data['transferRows'] as $row) {
                $rows[] = ['type' => 'row', 'cells' => $amountRow('  '.$row['name'], $row['months'], $row['total'])];
            }
        }

        $rows[] = ['type' => 'total', 'cells' => $amountRow(__('accounting.reports.net'), $data['netMonths'], $data['netTotal'])];

        return $rows;
    }

    /**
     * The per-account transfer breakdown for the year, scoped to the given accounts:
     * one row per account that had any internal move, each carrying its 12 signed
     * monthly sums and a total, plus the per-month totals across those accounts (which
     * net to zero only when all accounts are included).
     *
     * @param  list<int>  $accountIds
     * @return array{0: list<array{id:int, name:string, months:list<int>, total:int}>, 1: array<int, int>}
     */
    private function transfers(int $year, array $accountIds): array
    {
        $transfers = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->where('kind', BookingKind::Transfer)
            ->whereIn('account_id', $accountIds)
            ->whereYear('booking_date', $year)
            ->get(['account_id', 'amount_cents', 'booking_date']);

        $perAccount = [];
        $months = array_fill(1, 12, 0);
        foreach ($transfers as $transfer) {
            $month = $transfer->booking_date->month;
            $perAccount[$transfer->account_id][$month] = ($perAccount[$transfer->account_id][$month] ?? 0) + $transfer->amount_cents;
            $months[$month] += $transfer->amount_cents;
        }

        $names = Account::whereIn('id', array_keys($perAccount))->pluck('name', 'id');

        $rows = collect($perAccount)
            ->map(fn (array $accountMonths, int $accountId): array => [
                'id' => (int) $accountId,
                'name' => $names[$accountId] ?? '',
                'months' => collect(range(1, 12))->map(fn (int $m): int => $accountMonths[$m] ?? 0)->all(),
                'total' => array_sum($accountMonths),
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return [$rows, $months];
    }

    /**
     * The category rows for one direction, in tree order, dropping categories with
     * no activity this year. Each row carries its 12 monthly sums and a row total.
     *
     * @param  Collection<int, array<string, mixed>>  $flat
     * @param  array<int, array<int, int>>  $totals
     * @return list<array{id:int, parent_id:?int, name:string, depth:int, months:list<int>, total:int}>
     */
    private function rows(Collection $flat, array $totals, CategoryDirection $direction): array
    {
        return $flat
            ->where('direction', $direction->value)
            ->map(fn (array $c): array => [
                'id' => $c['id'],
                'parent_id' => $c['parent_id'],
                'name' => $c['name'],
                'depth' => $c['depth'],
                'months' => collect(range(1, 12))->map(fn (int $m): int => $totals[$c['id']][$m] ?? 0)->all(),
                'total' => collect(range(1, 12))->sum(fn (int $m): int => $totals[$c['id']][$m] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['total'] !== 0)
            ->values()
            ->all();
    }

    /**
     * Every year from the oldest to the newest confirmed booking, newest first, so
     * the picker has no gaps (falls back to this year when the ledger is empty).
     *
     * @return Collection<int, int>
     */
    private function availableYears(): Collection
    {
        $confirmed = Booking::where('status', BookingStatus::Confirmed);
        $newest = $confirmed->clone()->max('booking_date');
        $oldest = $confirmed->clone()->min('booking_date');

        if ($newest === null) {
            return collect([(int) now()->year]);
        }

        return collect(range(CarbonImmutable::parse($newest)->year, CarbonImmutable::parse($oldest)->year));
    }
}
