<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HolidayPeriod;
use App\Models\User;

/**
 * Managing Schließzeiten is for staff and admins. Admin is an axis of its own
 * (`is_admin`, independent of role), so an admin currently sitting in the parent
 * role keeps the rights. Reading is open to everyone — parents plan their own
 * holidays around the Hort's.
 */
class HolidayPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, HolidayPeriod $holidayPeriod): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, HolidayPeriod $holidayPeriod): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }
}
