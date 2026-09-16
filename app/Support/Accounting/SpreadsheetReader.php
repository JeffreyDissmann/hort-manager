<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads the first sheet of an Excel (.xls/.xlsx) or OpenDocument (.ods) bank export
 * into the same raw table {@see CsvReader} produces, so the mapping step can't tell
 * the two apart. Every cell becomes a string {@see StatementMapper} already parses:
 * date-formatted cells as `Y-m-d`, numbers dot-decimal, text as written.
 */
class SpreadsheetReader
{
    /** @var list<string> */
    public const EXTENSIONS = ['xls', 'xlsx', 'ods'];

    /**
     * Null when the file isn't a readable spreadsheet (corrupt, encrypted, or a
     * different format behind a spreadsheet extension).
     *
     * @return array{header: list<string>, rows: list<list<string>>}|null
     */
    public function read(string $path): ?array
    {
        try {
            // Only the real spreadsheet readers — never let a renamed HTML/CSV file
            // slip through PhpSpreadsheet's format auto-detection.
            $reader = IOFactory::createReaderForFile($path, [IOFactory::READER_XLS, IOFactory::READER_XLSX, IOFactory::READER_ODS]);
            $reader->setReadEmptyCells(false);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (SpreadsheetException) {
            return null;
        }

        $lines = array_values(array_filter(
            $this->lines($sheet),
            fn (array $cells): bool => $cells !== [],
        ));

        if ($lines === []) {
            return ['header' => [], 'rows' => []];
        }

        return ['header' => $lines[0], 'rows' => array_slice($lines, 1)];
    }

    /**
     * Each sheet row as its cell texts, trailing blanks dropped (an empty row → []).
     *
     * @return list<list<string>>
     */
    private function lines(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $lines = [];

        for ($row = 1, $lastRow = $sheet->getHighestDataRow(); $row <= $lastRow; $row++) {
            $cells = [];
            for ($column = 1; $column <= $lastColumn; $column++) {
                $cells[] = $sheet->cellExists([$column, $row]) ? $this->text($sheet->getCell([$column, $row])) : '';
            }

            while ($cells !== [] && end($cells) === '') {
                array_pop($cells);
            }

            $lines[] = $cells;
        }

        return $lines;
    }

    private function text(Cell $cell): string
    {
        try {
            $value = $cell->getCalculatedValue();
        } catch (SpreadsheetException) {
            // A formula PhpSpreadsheet can't evaluate: fall back to Excel's cached result.
            $value = $cell->getOldCalculatedValue();
        }

        if (is_int($value) || is_float($value)) {
            if (Date::isDateTime($cell)) {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            }

            // Whole numbers stay whole (account numbers); the rest get two decimals,
            // which StatementMapper reads as a decimal point rather than thousands.
            return floor($value) === (float) $value && abs($value) < 1e15
                ? (string) (int) $value
                : number_format((float) $value, 2, '.', '');
        }

        return trim((string) $value);
    }
}
