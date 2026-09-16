<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Writes tagged rows to a CSV or XLSX download. Each row is
 * ['type' => 'head'|'total'|'row', 'cells' => [...]]; the type drives XLSX styling
 * (bold/shaded header and total rows, a #,##0.00 number format on data rows). CSV
 * ignores styling and writes plain dot-decimal numbers so any importer parses them.
 */
class SpreadsheetExport
{
    private const NUMBER_FORMAT = '#,##0.00';

    /**
     * @var array<string, array{bold: bool, fill: string|null, numbers: bool}>
     */
    private const STYLES = [
        'head' => ['bold' => true, 'fill' => 'DCE9E7', 'numbers' => false],
        'total' => ['bold' => true, 'fill' => 'EFEFEF', 'numbers' => true],
        'row' => ['bold' => false, 'fill' => null, 'numbers' => true],
    ];

    /**
     * @param  list<array{type:string, cells:list<string|float>}>  $rows
     */
    public static function download(array $rows, string $basename, bool $xlsx): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'export');

        $xlsx ? self::writeXlsx($rows, $path) : self::writeCsv($rows, $path);

        return response()->download($path, "{$basename}.".($xlsx ? 'xlsx' : 'csv'))->deleteFileAfterSend();
    }

    /**
     * @param  list<array{type:string, cells:list<string|float>}>  $rows
     */
    private static function writeCsv(array $rows, string $path): void
    {
        $handle = fopen($path, 'w');

        // German Excel splits ;-delimited CSVs into columns; the BOM keeps umlauts.
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as ['cells' => $cells]) {
            // Plain, machine-readable amounts: dot decimal, 2 places, no thousands.
            fputcsv($handle, array_map(
                fn ($v) => is_int($v) || is_float($v) ? number_format((float) $v, 2, '.', '') : $v,
                $cells,
            ), ';', '"', '');
        }

        fclose($handle);
    }

    /**
     * @param  list<array{type:string, cells:list<string|float>}>  $rows
     */
    private static function writeXlsx(array $rows, string $path): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $index => ['type' => $type, 'cells' => $cells]) {
            $row = $index + 1;

            foreach (array_values($cells) as $column => $value) {
                // Explicit types: a purpose starting with „=" must stay text, never a
                // formula, and „0123"-like strings must not be turned into numbers.
                $sheet->setCellValueExplicit(
                    [$column + 1, $row],
                    $value,
                    is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
                );
            }

            self::styleRow($sheet, $row, count($cells), self::STYLES[$type] ?? self::STYLES['row']);
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @param  array{bold: bool, fill: string|null, numbers: bool}  $style
     */
    private static function styleRow(Worksheet $sheet, int $row, int $columns, array $style): void
    {
        if ($columns === 0) {
            return;
        }

        $range = "A{$row}:".Coordinate::stringFromColumnIndex($columns).$row;
        $cells = $sheet->getStyle($range);

        $cells->getFont()->setBold($style['bold']);
        if ($style['fill'] !== null) {
            $cells->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($style['fill']);
        }
        if ($style['numbers']) {
            $cells->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        }
    }
}
