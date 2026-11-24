<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StandbyDuty;
use App\Models\User;

/**
 * The Springerdienst list is read by everyone (families plan around their dates and
 * need to see the others' to swap). Filling it is for the „Springer-Koordination"
 * flag and admins — deliberately not staff by role: the Amt sits with a parent.
 */
class StandbyDutyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Assign, change or clear any slot, and run the fair fill. */
    public function manage(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, StandbyDuty $standbyDuty): bool
    {
        return $this->manages($user);
    }

    /** Admin is an axis of its own, so an admin always may — with or without the flag. */
    private function manages(User $user): bool
    {
        return $user->manages_standby || $user->isAdmin();
    }
}
