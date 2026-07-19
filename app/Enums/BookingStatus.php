<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A booking's review lifecycle: freshly imported from CSV (draft), analysed by
 * the AI with category/counterparty suggestions (suggested), or reviewed and
 * accepted by a human (confirmed). Manual bookings are created confirmed.
 */
enum BookingStatus: string
{
    case Draft = 'draft';
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return __('enums.booking_status.'.$this->value);
    }
}
