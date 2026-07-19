<?php

declare(strict_types=1);

namespace App\Enums;

/** A booking is a draft (imported, awaiting review) until a reviewer confirms it. */
enum BookingStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return __('enums.booking_status.'.$this->value);
    }
}
