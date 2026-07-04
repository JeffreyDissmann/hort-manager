<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The live state of a child on a given day (the Tagesboard). Fixed set.
 */
enum DepartureStatus: string
{
    case Present = 'present';
    case PickedUp = 'picked_up';
    case SentHome = 'sent_home';
    case Excursion = 'excursion';

    /** Localised label for the UI (de/en, per the active locale). */
    public function label(): string
    {
        return __('enums.departure_status.'.$this->value);
    }

    /** Whether the child has already left the Hort. */
    public function hasLeft(): bool
    {
        return $this === self::PickedUp || $this === self::SentHome;
    }
}
