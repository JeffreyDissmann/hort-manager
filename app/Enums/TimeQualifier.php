<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Qualifies a "geht allein" (sent_home) pickup time: whether the child must leave
 * by that time, exactly at it, or any time from it onwards. Only meaningful for the
 * sent_home method; a null qualifier is treated as `At`.
 */
enum TimeQualifier: string
{
    case By = 'by';     // bis zu der Uhrzeit
    case At = 'at';     // genau zur Uhrzeit
    case From = 'from'; // ab der Uhrzeit (oder später)

    /** Localised label for the UI (de/en, per the active locale). */
    public function label(): string
    {
        return __('enums.time_qualifier.'.$this->value);
    }

    /** Short prefix shown before the time on plans/board, e.g. „ab 15:30". */
    public function prefix(): string
    {
        return __('enums.time_qualifier_prefix.'.$this->value);
    }
}
