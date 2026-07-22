<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Booking;
use App\Support\Accounting\CategoryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin-only „Auswertung": a month × category pivot of the confirmed ledger for a
 * chosen year. Income and expense are shown as separate blocks (categories rolled
 * up over their subtree) with per-month and per-category totals and a monthly net.
 * Transfers are internal moves and are excluded.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $years = $this->availableYears();
        $year = $request->integer('year') ?: (int) $years->first();

        return Inertia::render('Accounting/Reports/Index', [
            'year' => $year,
            'years' => $years,
            ...$this->data($year),
        ]);
    }

    /** Download the year's pivot as CSV (German ;-delimited) or XLSX. */
    public function export(Request $request): BinaryFileResponse
    {
        $year = $request->integer('year') ?: (int) $this->availableYears()->first();
        $xlsx = strtolower((string) $request->string('format')) === 'xlsx';

        $writer = $xlsx ? new XlsxWriter : new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';'));
        $path = tempnam(sys_get_temp_dir(), 'report');
        $writer->openToFile($path);

        // XLSX: bold, shaded header/total rows, and a #,##0.00 number format so amounts
        // display thousand-separated with 2 decimals while staying real (summable) numbers.
        $base = new Style;
        $styles = [
            'head' => $base->withFontBold(true)->withBackgroundColor('DCE9E7'),
            'total' => $base->withFontBold(true)->withBackgroundColor('EFEFEF')->withFormat('#,##0.00'),
            'row' => $base->withFormat('#,##0.00'),
        ];

        foreach ($this->matrix($this->data($year)) as ['type' => $type, 'cells' => $cells]) {
            if ($xlsx) {
                $writer->addRow(Row::fromValuesWithStyle($cells, $styles[$type]));

                continue;
            }
            // CSV stays machine-readable: plain dot-decimal, 2 places, no thousands
            // separator — so any importer parses the amounts cleanly.
            $writer->addRow(Row::fromValues(array_map(
                fn ($v) => is_int($v) || is_float($v) ? number_format((float) $v, 2, '.', '') : $v,
                $cells,
            )));
        }

        $writer->close();

        return response()->download($path, "report-{$year}.".($xlsx ? 'xlsx' : 'csv'))->deleteFileAfterSend();
    }

    /**
     * The full month × category pivot for a year, shared by the view and the export.
     *
     * @return array<string, mixed>
     */
    private function data(int $year): array
    {
        // Confirmed, categorised, non-transfer bookings for the year (a Hort's ledger
        // is small enough to bucket per category/month in PHP — no DB-specific SQL).
        $bookings = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->where('kind', '!=', BookingKind::Transfer)
            ->whereNotNull('category_id')
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

        $rows[] = ['type' => 'total', 'cells' => $amountRow(__('accounting.reports.net'), $data['netMonths'], $data['netTotal'])];

        return $rows;
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
