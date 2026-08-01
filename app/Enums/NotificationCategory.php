<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

/** The user-facing notification types a user can toggle per channel. */
enum NotificationCategory: string
{
    case Departures = 'departures';
    case Excursions = 'excursions';
    case Companion = 'companion';
    case MissingPlan = 'missing_plan';
    case WeeklyDigest = 'weekly_digest';
    case LateChange = 'late_change';

    /** Who this category is sent to. */
    public function audience(): NotificationAudience
    {
        return match ($this) {
            self::LateChange => NotificationAudience::Staff,
            default => NotificationAudience::Guardian,
        };
    }

    /** Whether this user can ever receive this category (and so may toggle it). */
    public function isRelevantFor(User $user): bool
    {
        return $this->audience()->matches($user);
    }

    /**
     * The categories this user may toggle, in enum order.
     *
     * @return list<self>
     */
    public static function for(User $user): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $category): bool => $category->isRelevantFor($user),
        ));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
