<?php

declare(strict_types=1);

namespace App\Enums;

enum AbsenceReason: string
{
    case Sick = 'sick';
    case Away = 'away';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Krank',
            self::Away => 'Abwesend',
        };
    }
}
