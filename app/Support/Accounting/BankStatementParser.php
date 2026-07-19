<?php

declare(strict_types=1);

namespace App\Support\Accounting;

/**
 * Parses a bank statement export into normalized rows. One implementation per
 * bank/CSV format.
 */
interface BankStatementParser
{
    /**
     * @return list<array{booking_date:string, valuta_date:string, purpose:string, amount_cents:int, currency:string}>
     */
    public function parse(string $contents): array;
}
