<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

/** Who a notification category is meant for — drives which toggles a user is shown. */
enum NotificationAudience: string
{
    /** Parents (and staff who are a guardian themselves). */
    case Guardian = 'guardian';

    /** Erzieher:innen. */
    case Staff = 'staff';

    /**
     * Whether this audience applies to the given user.
     *
     * Guardian categories stay visible for every non-staff user even before a child
     * is linked to them; staff only see them once they are a guardian themselves.
     */
    public function matches(User $user): bool
    {
        return match ($this) {
            self::Guardian => ! $user->isStaff() || $user->children()->exists(),
            self::Staff => $user->isStaff(),
        };
    }
}
