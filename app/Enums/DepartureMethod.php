<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a child leaves the Hort. This is a fixed set — do not make it configurable.
 * `with_child` mirrors another child's pickup (see DailyDeparture::companion); the
 * board states `excursion` and `present` live on DailyDeparture's status.
 */
enum DepartureMethod: string
{
    case PickedUp = 'picked_up';
    case SentHome = 'sent_home';
    case WithChild = 'with_child';

    /** Localised label for the UI (de/en, per the active locale). */
    public function label(): string
    {
        return __('enums.departure_method.'.$this->value);
    }
}
