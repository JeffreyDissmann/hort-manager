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

    /** German label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Present => 'Noch da',
            self::PickedUp => 'Abgeholt',
            self::SentHome => 'Nach Hause geschickt',
            self::Excursion => 'Ausflug',
        };
    }

    /** Whether the child has already left the Hort. */
    public function hasLeft(): bool
    {
        return $this === self::PickedUp || $this === self::SentHome;
    }
}
