<?php

declare(strict_types=1);

namespace App\Enums;

/** The user-facing notification types a user can toggle per channel. */
enum NotificationCategory: string
{
    case Departures = 'departures';
    case Excursions = 'excursions';
    case Companion = 'companion';
    case MissingPlan = 'missing_plan';
    case WeeklyDigest = 'weekly_digest';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
