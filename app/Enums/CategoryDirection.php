<?php

declare(strict_types=1);

namespace App\Enums;

/** Whether a booking category records money coming in or going out. */
enum CategoryDirection: string
{
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return __('enums.category_direction.'.$this->value);
    }

    /** The sign a booking's amount must carry under this direction (+1 in, -1 out). */
    public function sign(): int
    {
        return $this === self::Income ? 1 : -1;
    }
}
