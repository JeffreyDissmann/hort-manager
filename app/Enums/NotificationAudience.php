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
     * Whoever fills the Springerdienst list („Springer-Koordination" flag). Admins are
     * not included: they may edit the list, but the Amt — and its mail — sits with
     * the flagged person.
     */
    case StandbyCoordinator = 'standby_coordinator';

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
            self::StandbyCoordinator => (bool) $user->manages_standby,
        };
    }
}
