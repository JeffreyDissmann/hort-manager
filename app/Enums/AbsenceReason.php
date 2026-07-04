<?php

declare(strict_types=1);

namespace App\Enums;

enum AbsenceReason: string
{
    case Sick = 'sick';
    case Away = 'away';

    public function label(): string
    {
        return __('enums.absence_reason.'.$this->value);
    }
}
