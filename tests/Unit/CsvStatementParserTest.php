<?php

declare(strict_types=1);

use App\Support\Accounting\CsvStatementParser;

function utf16le(string $utf8): string
{
    return mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
}

it('decodes UTF-16, skips the header and parses German dates and amounts', function () {
    $csv = "Kontonummer;Buchungsdatum;Valuta;Verwendungszweck;Betrag;Waehrung\r\n"
        ."12345;01.04.2026;01.04.2026;SEPA-GUTSCHRIFT Essensgeld;50,00;EUR\r\n"
        ."12345;01.04.2026;31.03.2026;DAUERAUFTRAG Miete;-3.520,00;EUR\r\n";

    $rows = (new CsvStatementParser)->parse(utf16le($csv));

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray([
            'booking_date' => '2026-04-01',
            'valuta_date' => '2026-04-01',
            'amount_cents' => 5000,
            'currency' => 'EUR',
        ])
        ->and($rows[0]['purpose'])->toContain('Essensgeld')
        ->and($rows[1]['amount_cents'])->toBe(-352000)
        ->and($rows[1]['valuta_date'])->toBe('2026-03-31');
});

it('keeps a semicolon that appears inside the purpose', function () {
    $csv = "Kontonummer;Buchungsdatum;Valuta;Verwendungszweck;Betrag;Waehrung\r\n"
        ."12345;02.04.2026;02.04.2026;Miete; Nebenkosten; Kaution;-100,00;EUR\r\n";

    $rows = (new CsvStatementParser)->parse(utf16le($csv));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['purpose'])->toBe('Miete; Nebenkosten; Kaution')
        ->and($rows[0]['amount_cents'])->toBe(-10000);
});

it('collapses the whitespace runs the export scatters through the purpose', function () {
    $csv = "Kontonummer;Buchungsdatum;Valuta;Verwendungszweck;Betrag;Waehrung\r\n"
        ."12345;03.04.2026;03.04.2026;Bezahlung   mit    Karte     Busine ss;-14,00;EUR\r\n";

    $rows = (new CsvStatementParser)->parse(utf16le($csv));

    expect($rows[0]['purpose'])->toBe('Bezahlung mit Karte Busine ss');
});

it('also reads a plain UTF-8 file', function () {
    $csv = "Kontonummer;Buchungsdatum;Valuta;Verwendungszweck;Betrag;Waehrung\n"
        ."12345;05.04.2026;05.04.2026;Test Ümlaut;12,34;EUR\n";

    $rows = (new CsvStatementParser)->parse($csv);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['amount_cents'])->toBe(1234)
        ->and($rows[0]['purpose'])->toBe('Test Ümlaut');
});
