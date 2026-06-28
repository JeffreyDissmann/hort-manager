<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/** The Tagesprogramm is Hort-wide and staff-managed (everyone may read it elsewhere). */
class DailyProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user): bool
    {
        return $user->isStaff();
    }
}
