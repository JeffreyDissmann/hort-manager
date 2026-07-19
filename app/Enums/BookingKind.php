<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a booking represents. Income/expense mirror the category's direction;
 * transfer is money moved between two own accounts (netted out of reports).
 */
enum BookingKind: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    public function label(): string
    {
        return __('enums.booking_kind.'.$this->value);
    }
}
