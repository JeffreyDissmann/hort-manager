<?php

declare(strict_types=1);

use App\Support\Accounting\SpreadsheetExport;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** @return list<array{type:string, cells:list<string|float>}> */
function exportRows(): array
{
    return [
        ['type' => 'head', 'cells' => ['Datum', 'Verwendungszweck', 'Betrag']],
        ['type' => 'row', 'cells' => ['2026-01-10', 'Essensgeld Müller; März', 1234.5]],
        ['type' => 'row', 'cells' => ['2026-01-11', '=HYPERLINK("x")', -20.0]],
        ['type' => 'total', 'cells' => ['Summe', '', 1214.5]],
    ];
}

it('writes a ;-separated CSV with a BOM and plain dot-decimal amounts', function () {
    $response = SpreadsheetExport::download(exportRows(), 'report', xlsx: false);

    expect($response->getFile()->getFilename())->not->toBeEmpty()
        ->and(file_get_contents($response->getFile()->getPathname()))->toBe(
            "\xEF\xBB\xBF"
            ."Datum;Verwendungszweck;Betrag\n"
            ."2026-01-10;\"Essensgeld Müller; März\";1234.50\n"
            ."2026-01-11;\"=HYPERLINK(\"\"x\"\")\";-20.00\n"
            ."Summe;;1214.50\n"
        );
});

it('writes a styled XLSX whose amounts are numbers and whose text stays text', function () {
    $response = SpreadsheetExport::download(exportRows(), 'report', xlsx: true);

    $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();

    // Values: numbers stay numeric, a formula-looking purpose is stored as plain text.
    expect($sheet->getCell('C2')->getValue())->toEqual(1234.5)
        ->and($sheet->getCell('C2')->getDataType())->toBe(DataType::TYPE_NUMERIC)
        ->and($sheet->getCell('B3')->getValue())->toBe('=HYPERLINK("x")')
        ->and($sheet->getCell('B3')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getCell('A2')->getDataType())->toBe(DataType::TYPE_STRING);

    // Styling: shaded bold header, bold shaded total, number format on data and totals.
    expect($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getStyle('C1')->getFill()->getStartColor()->getRGB())->toBe('DCE9E7')
        ->and($sheet->getStyle('C2')->getFont()->getBold())->toBeFalse()
        ->and($sheet->getStyle('C2')->getNumberFormat()->getFormatCode())->toBe('#,##0.00')
        ->and($sheet->getStyle('A4')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getStyle('C4')->getFill()->getStartColor()->getRGB())->toBe('EFEFEF')
        ->and($sheet->getStyle('C4')->getNumberFormat()->getFormatCode())->toBe('#,##0.00');
});
