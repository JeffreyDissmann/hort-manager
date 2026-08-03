<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Writes tagged rows to a CSV or XLSX download. Each row is
 * ['type' => 'head'|'total'|'row', 'cells' => [...]]; the type drives XLSX styling
 * (bold/shaded header and total rows, a #,##0.00 number format on data rows). CSV
 * ignores styling and writes plain dot-decimal numbers so any importer parses them.
 */
class SpreadsheetExport
{
    /**
     * @param  list<array{type:string, cells:list<string|float>}>  $rows
     */
    public static function download(array $rows, string $basename, bool $xlsx): BinaryFileResponse
    {
        // German Excel splits ;-delimited CSVs into columns; the BOM keeps umlauts.
        $writer = $xlsx ? new XlsxWriter : new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';'));
        $path = tempnam(sys_get_temp_dir(), 'export');
        $writer->openToFile($path);

        $base = new Style;
        $styles = [
            'head' => $base->withFontBold(true)->withBackgroundColor('DCE9E7'),
            'total' => $base->withFontBold(true)->withBackgroundColor('EFEFEF')->withFormat('#,##0.00'),
            'row' => $base->withFormat('#,##0.00'),
        ];

        foreach ($rows as ['type' => $type, 'cells' => $cells]) {
            if ($xlsx) {
                $writer->addRow(Row::fromValuesWithStyle($cells, $styles[$type] ?? $styles['row']));

                continue;
            }
            // Plain, machine-readable amounts: dot decimal, 2 places, no thousands.
            $writer->addRow(Row::fromValues(array_map(
                fn ($v) => is_int($v) || is_float($v) ? number_format((float) $v, 2, '.', '') : $v,
                $cells,
            )));
        }

        $writer->close();

        return response()->download($path, "{$basename}.".($xlsx ? 'xlsx' : 'csv'))->deleteFileAfterSend();
    }
}
