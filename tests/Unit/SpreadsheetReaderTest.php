<?php

declare(strict_types=1);

use App\Support\Accounting\SpreadsheetReader;
use App\Support\Accounting\StatementMapper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Write rows to a temp workbook of the given writer type (Xls, Xlsx, Ods) and return
 * its path. Values are set as given, so numbers stay numbers and text stays text.
 *
 * @param  list<list<mixed>>  $rows
 */
function statementWorkbook(array $rows, string $type = 'Xls', ?callable $style = null): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, strictNullComparison: true);
    if ($style !== null) {
        $style($spreadsheet->getActiveSheet());
    }

    $path = tempnam(sys_get_temp_dir(), 'statement');
    IOFactory::createWriter($spreadsheet, $type)->save($path);

    return $path;
}

it('reads a bank export whose cells are all text, like the real .xls download', function (string $type) {
    $path = statementWorkbook([
        ['IBAN', 'Konto', 'Verwendungszweck', 'Buchung', 'Valuta', 'Betrag'],
        ['DE00123', 'SCHÜLERLADEN e.V.', 'LASTSCHRIFT Krankenkasse Beiträge', '2026-08-27', '2026-08-26', '-2.396,78 EUR'],
        ['DE00123', 'SCHÜLERLADEN e.V.', 'SEPA-DAUERAUFTRAG Essensgeld', '2026-08-19', '2026-08-19', '65,00 EUR'],
    ], $type);

    $table = (new SpreadsheetReader)->read($path);
    $mapper = new StatementMapper;
    $rows = $mapper->normalize($table['rows'], $mapper->guess($table['header']));

    expect($table['header'])->toBe(['IBAN', 'Konto', 'Verwendungszweck', 'Buchung', 'Valuta', 'Betrag'])
        ->and($table['rows'])->toHaveCount(2)
        ->and($rows[0])->toMatchArray([
            'booking_date' => '2026-08-27',
            'valuta_date' => '2026-08-26',
            'purpose' => 'LASTSCHRIFT Krankenkasse Beiträge',
            'amount_cents' => -239678,
        ])
        ->and($rows[1]['amount_cents'])->toBe(6500);
})->with(['Xls', 'Xlsx', 'Ods']);

it('turns real date and number cells into strings the mapper parses', function () {
    $path = statementWorkbook([
        ['Buchungsdatum', 'Verwendungszweck', 'Betrag', 'Kontonummer'],
        [Date::stringToExcel('2026-04-01'), 'Miete', -3520.5, 12345],
        [Date::stringToExcel('2026-04-02'), 'Essensgeld', 50, 12345],
    ], 'Xlsx', function ($sheet) {
        $sheet->getStyle('A2:A3')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
    });

    $table = (new SpreadsheetReader)->read($path);

    expect($table['rows'])->toBe([
        ['2026-04-01', 'Miete', '-3520.50', '12345'],
        ['2026-04-02', 'Essensgeld', '50', '12345'],
    ]);
});

it('evaluates formulas and skips blank rows', function () {
    $path = statementWorkbook([
        ['Datum', 'Zweck', 'Betrag'],
        [null, null, null],
        ['01.04.2026', 'Summe', '=10.25+2'],
    ], 'Xlsx');

    expect((new SpreadsheetReader)->read($path)['rows'])->toBe([
        ['01.04.2026', 'Summe', '12.25'],
    ]);
});

it('returns null for a file that is not a spreadsheet', function () {
    $path = tempnam(sys_get_temp_dir(), 'statement');
    file_put_contents($path, "Datum;Zweck;Betrag\n01.04.2026;Miete;-10,00\n");

    expect((new SpreadsheetReader)->read($path))->toBeNull();
});
