<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a child leaves the Hort. This is a fixed set — do not make it configurable.
 * The board states `excursion` and `present` live on DailyDeparture, so the planned
 * method only ever holds one of these two values.
 */
enum DepartureMethod: string
{
    case PickedUp = 'picked_up';
    case SentHome = 'sent_home';

    /** German label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::PickedUp => 'Wird abgeholt',
            self::SentHome => 'Geht allein nach Hause',
        };
    }
}
