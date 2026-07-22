<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use Carbon\CarbonImmutable;

/**
 * Parses a typical German online-banking „Umsatzliste" CSV export: UTF-16,
 * semicolon-delimited, columns Kontonummer;Buchungsdatum;Valuta;Verwendungszweck;
 * Betrag;Waehrung with German dates (DD.MM.YYYY) and amounts (-3.520,00). Rows are
 * parsed positionally from both ends so a semicolon inside the purpose can't shift
 * the columns.
 */
class CsvStatementParser implements BankStatementParser
{
    /**
     * @return list<array{booking_date:string, valuta_date:string, purpose:string, amount_cents:int, currency:string}>
     */
    public function parse(string $contents): array
    {
        $text = $this->toUtf8($contents);
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = explode(';', $line);
            if (count($parts) < 6) {
                continue;
            }

            // Only rows whose date column holds a real date are transactions — this
            // skips the header and any preamble/summary noise without ever handing a
            // malformed value to the (strict) date parser.
            $bookingDate = trim($parts[1]);
            if (! $this->looksLikeDate($bookingDate)) {
                continue;
            }

            $currency = trim(array_pop($parts));
            $amount = trim(array_pop($parts));
            $valutaRaw = trim($parts[2]);
            $valutaDate = $this->looksLikeDate($valutaRaw) ? $valutaRaw : $bookingDate;
            $purpose = trim(implode(';', array_slice($parts, 3)));

            $rows[] = [
                'booking_date' => $this->date($bookingDate),
                'valuta_date' => $this->date($valutaDate),
                'purpose' => $this->tidy($purpose),
                'amount_cents' => $this->cents($amount),
                'currency' => $currency !== '' ? $currency : 'EUR',
            ];
        }

        return $rows;
    }

    /** Decode UTF-16 to UTF-8; pass through if already UTF-8. */
    private function toUtf8(string $contents): string
    {
        $bom = substr($contents, 0, 2);

        // A UTF-16 file is full of null bytes. These exports are little-endian
        // without a BOM, so fall back to LE unless a BOM says otherwise.
        if ($bom === "\xFE\xFF") {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-16BE');
        } elseif ($bom === "\xFF\xFE" || str_contains(substr($contents, 0, 200), "\0")) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-16LE');
        }

        // Strip a leading BOM if present.
        return preg_replace('/^\x{FEFF}/u', '', $contents) ?? $contents;
    }

    private function looksLikeDate(string $value): bool
    {
        return (bool) preg_match('/^\d{2}\.\d{2}\.\d{4}$/', trim($value));
    }

    private function date(string $value): string
    {
        return CarbonImmutable::createFromFormat('d.m.Y', trim($value))->toDateString();
    }

    /** German amount „-3.520,00" → signed cents. */
    private function cents(string $value): int
    {
        $normalized = str_replace(['.', ' '], '', trim($value)); // drop thousands sep + spaces
        $normalized = str_replace(',', '.', $normalized);        // decimal comma → point

        return (int) round((float) $normalized * 100);
    }

    /** Collapse the runs of whitespace the export scatters through the purpose. */
    private function tidy(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
