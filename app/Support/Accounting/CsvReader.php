<?php

declare(strict_types=1);

namespace App\Support\Accounting;

/**
 * Reads an arbitrary delimited bank export into a raw table: the delimiter it
 * detected, the header row, and the data rows (each a list of string cells).
 * Encoding (UTF-16/UTF-8) and delimiter (`;` `,` tab) are sniffed automatically;
 * the meaning of each column is decided later by the user via the mapping step.
 */
class CsvReader
{
    private const DELIMITERS = [';', ',', "\t", '|'];

    /**
     * @return array{delimiter: string, header: list<string>, rows: list<list<string>>}
     */
    public function read(string $contents): array
    {
        $text = $this->toUtf8($contents);
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($text)) ?: [],
            fn (string $line): bool => trim($line) !== '',
        ));

        if ($lines === []) {
            return ['delimiter' => ';', 'header' => [], 'rows' => []];
        }

        $delimiter = $this->sniffDelimiter($lines[0]);
        $header = $this->split($lines[0], $delimiter);

        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            $rows[] = $this->split($line, $delimiter);
        }

        return ['delimiter' => $delimiter, 'header' => $header, 'rows' => $rows];
    }

    /** @return list<string> */
    private function split(string $line, string $delimiter): array
    {
        return array_map('trim', str_getcsv($line, $delimiter, '"', '\\'));
    }

    /** Pick the delimiter that yields the most columns on the header line. */
    private function sniffDelimiter(string $header): string
    {
        $best = ';';
        $bestCount = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $count = count($this->split($header, $delimiter));
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $delimiter;
            }
        }

        return $best;
    }

    /** Decode UTF-16 to UTF-8; pass through if already UTF-8, then strip a BOM. */
    private function toUtf8(string $contents): string
    {
        $bom = substr($contents, 0, 2);

        // A UTF-16 file is full of null bytes. German bank exports are typically
        // little-endian without a BOM, so fall back to LE unless a BOM says otherwise.
        if ($bom === "\xFE\xFF") {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-16BE');
        } elseif ($bom === "\xFF\xFE" || str_contains(substr($contents, 0, 200), "\0")) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-16LE');
        }

        return preg_replace('/^\x{FEFF}/u', '', $contents) ?? $contents;
    }
}
