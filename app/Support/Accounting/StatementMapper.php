<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Turns the raw table from {@see CsvReader} into normalized statement rows using a
 * user-confirmed column mapping (field → column index). Date and amount formats are
 * auto-detected per value, so the user only has to point each column at a field.
 *
 * Fields: `booking_date`, `amount` and `purpose` are required; `valuta_date`
 * (defaults to the booking date) and `currency` (defaults to EUR) are optional.
 */
class StatementMapper
{
    /** @var list<string> */
    public const FIELDS = ['booking_date', 'valuta_date', 'purpose', 'amount', 'currency'];

    /** @var list<string> */
    public const REQUIRED_FIELDS = ['booking_date', 'purpose', 'amount'];

    /**
     * Header keywords (lowercased, substring match) that hint at each field, best
     * guess first.
     *
     * @var array<string, list<string>>
     */
    private const HINTS = [
        'booking_date' => ['buchungstag', 'buchungsdatum', 'booking', 'datum', 'date'],
        'valuta_date' => ['valuta', 'wertstellung', 'value'],
        'purpose' => ['verwendungszweck', 'buchungstext', 'purpose', 'zweck', 'beschreibung', 'text', 'description'],
        'amount' => ['betrag', 'umsatz', 'amount', 'wert'],
        'currency' => ['waehrung', 'währung', 'currency', 'wkz'],
    ];

    /**
     * Best-guess mapping of each field to a header column index (null if none fits).
     * Each column is claimed at most once.
     *
     * @param  list<string>  $header
     * @return array<string, int|null>
     */
    public function guess(array $header): array
    {
        $normalized = array_map(fn (string $h): string => mb_strtolower(trim($h)), $header);
        $mapping = array_fill_keys(self::FIELDS, null);
        $taken = [];

        foreach (self::HINTS as $field => $hints) {
            foreach ($hints as $hint) {
                foreach ($normalized as $index => $name) {
                    if (! isset($taken[$index]) && $name !== '' && str_contains($name, $hint)) {
                        $mapping[$field] = $index;
                        $taken[$index] = true;

                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Apply a mapping to the raw rows, dropping any whose booking-date cell isn't a
     * real date (header repeats, preamble/summary noise, short rows).
     *
     * @param  list<list<string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return list<array{booking_date:string, valuta_date:string, purpose:string, amount_cents:int, currency:string}>
     */
    public function normalize(array $rows, array $mapping): array
    {
        $result = [];

        foreach ($rows as $row) {
            $bookingRaw = $this->cell($row, $mapping['booking_date'] ?? null);
            $bookingDate = $this->date($bookingRaw);
            if ($bookingDate === null) {
                continue;
            }

            $valutaDate = $this->date($this->cell($row, $mapping['valuta_date'] ?? null)) ?? $bookingDate;
            $currency = strtoupper($this->cell($row, $mapping['currency'] ?? null));

            $result[] = [
                'booking_date' => $bookingDate,
                'valuta_date' => $valutaDate,
                'purpose' => $this->tidy($this->cell($row, $mapping['purpose'] ?? null)),
                'amount_cents' => $this->cents($this->cell($row, $mapping['amount'] ?? null)),
                'currency' => $currency !== '' ? $currency : 'EUR',
            ];
        }

        return $result;
    }

    /**
     * @param  list<string>  $row
     */
    private function cell(array $row, ?int $index): string
    {
        return $index !== null ? trim($row[$index] ?? '') : '';
    }

    /** Parse a date in whichever common layout it's written; null if it isn't one. */
    private function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['d.m.Y', 'Y-m-d', 'd/m/Y', 'd.m.y', 'd-m-Y'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat("!{$format}", $value);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->toDateString();
            }
        }

        return null;
    }

    /**
     * Parse an amount into signed cents, auto-detecting the decimal separator: the
     * last of „.,“ is the decimal point, the other is a thousands separator. „50,00“
     * → 5000, „-3.520,00“ → -352000, „1234.56“ → 123456, „3.520“ → 352000.
     */
    private function cents(string $value): int
    {
        $value = preg_replace('/[^\d.,\-]/', '', trim($value)) ?? '';
        if ($value === '' || $value === '-') {
            return 0;
        }

        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // Both present: the later one is the decimal separator.
            $decimal = $lastDot > $lastComma ? '.' : ',';
            $thousands = $decimal === '.' ? ',' : '.';
            $value = str_replace($thousands, '', $value);
            $value = str_replace($decimal, '.', $value);
        } elseif ($lastComma !== false) {
            // Only a comma → decimal comma (German).
            $value = str_replace(',', '.', $value);
        } elseif ($lastDot !== false) {
            // Only a dot: decimal when exactly two digits trail it, else thousands.
            $value = strlen($value) - $lastDot - 1 === 2 ? $value : str_replace('.', '', $value);
        }

        return (int) round((float) $value * 100);
    }

    /**
     * Collapse the whitespace runs the export scatters through the purpose, and drop
     * any leading empty columns some banks pad it with.
     */
    private function tidy(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return ltrim($value, '; ');
    }
}
