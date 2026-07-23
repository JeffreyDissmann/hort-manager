<?php

declare(strict_types=1);

use App\Support\Accounting\CsvReader;
use App\Support\Accounting\StatementMapper;

function utf16le(string $utf8): string
{
    return mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
}

it('decodes UTF-16 and splits the header and rows on the detected delimiter', function () {
    $csv = "Kontonummer;Buchungsdatum;Valuta;Verwendungszweck;Betrag;Waehrung\r\n"
        ."12345;01.04.2026;31.03.2026;SEPA-GUTSCHRIFT Essensgeld;50,00;EUR\r\n";

    $table = (new CsvReader)->read(utf16le($csv));

    expect($table['delimiter'])->toBe(';')
        ->and($table['header'])->toBe(['Kontonummer', 'Buchungsdatum', 'Valuta', 'Verwendungszweck', 'Betrag', 'Waehrung'])
        ->and($table['rows'])->toHaveCount(1)
        ->and($table['rows'][0][3])->toBe('SEPA-GUTSCHRIFT Essensgeld');
});

it('reads a comma-delimited UTF-8 file too', function () {
    $csv = "Date,Purpose,Amount\n2026-04-05,Test Ümlaut,12.34\n";

    $table = (new CsvReader)->read($csv);

    expect($table['delimiter'])->toBe(',')
        ->and($table['header'])->toBe(['Date', 'Purpose', 'Amount'])
        ->and($table['rows'][0])->toBe(['2026-04-05', 'Test Ümlaut', '12.34']);
});

it('guesses the column mapping from German header names', function () {
    $header = ['Kontonummer', 'Buchungsdatum', 'Valuta', 'Verwendungszweck', 'Betrag', 'Waehrung'];

    expect((new StatementMapper)->guess($header))->toMatchArray([
        'booking_date' => 1,
        'valuta_date' => 2,
        'purpose' => 3,
        'amount' => 4,
        'currency' => 5,
    ]);
});

it('leaves a field unmapped when no header matches', function () {
    $header = ['Datum', 'Text', 'Betrag']; // no valuta, no currency column

    $mapping = (new StatementMapper)->guess($header);

    expect($mapping['booking_date'])->toBe(0)
        ->and($mapping['purpose'])->toBe(1)
        ->and($mapping['amount'])->toBe(2)
        ->and($mapping['valuta_date'])->toBeNull()
        ->and($mapping['currency'])->toBeNull();
});

it('normalizes rows with the mapping, auto-detecting German dates and amounts', function () {
    $rows = [
        ['12345', '01.04.2026', '31.03.2026', 'Essensgeld', '50,00', 'EUR'],
        ['12345', '02.04.2026', '02.04.2026', 'Miete', '-3.520,00', 'EUR'],
    ];
    $mapping = ['booking_date' => 1, 'valuta_date' => 2, 'purpose' => 3, 'amount' => 4, 'currency' => 5];

    $normalized = (new StatementMapper)->normalize($rows, $mapping);

    expect($normalized)->toHaveCount(2)
        ->and($normalized[0])->toMatchArray([
            'booking_date' => '2026-04-01',
            'valuta_date' => '2026-03-31',
            'purpose' => 'Essensgeld',
            'amount_cents' => 5000,
            'currency' => 'EUR',
        ])
        ->and($normalized[1]['amount_cents'])->toBe(-352000);
});

it('auto-detects dot-decimal amounts and ISO dates', function () {
    $rows = [['2026-04-05', 'Card payment', '1234.56']];
    $mapping = ['booking_date' => 0, 'valuta_date' => null, 'purpose' => 1, 'amount' => 2, 'currency' => null];

    $normalized = (new StatementMapper)->normalize($rows, $mapping);

    expect($normalized[0])->toMatchArray([
        'booking_date' => '2026-04-05',
        'valuta_date' => '2026-04-05', // falls back to the booking date
        'amount_cents' => 123456,
        'currency' => 'EUR',           // defaults when unmapped
    ]);
});

it('treats a lone dot with three trailing digits as a thousands separator', function () {
    $rows = [['01.04.2026', 'Grosse Zahlung', '3.520']];
    $mapping = ['booking_date' => 0, 'valuta_date' => null, 'purpose' => 1, 'amount' => 2, 'currency' => null];

    expect((new StatementMapper)->normalize($rows, $mapping)[0]['amount_cents'])->toBe(352000);
});

it('drops rows whose booking-date cell is not a real date', function () {
    $rows = [
        ['Kontostand', 'Vortrag', 'kein Datum', '123,45'], // summary noise
        ['01.04.2026', '01.04.2026', 'REWE', '-50,00'],
    ];
    $mapping = ['booking_date' => 0, 'valuta_date' => 1, 'purpose' => 2, 'amount' => 3, 'currency' => null];

    $normalized = (new StatementMapper)->normalize($rows, $mapping);

    expect($normalized)->toHaveCount(1)
        ->and($normalized[0]['purpose'])->toBe('REWE');
});
