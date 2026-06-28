<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Excursion;
use App\Models\User;

/** Managing excursions (the staff-only trip admin; the parent poll lives elsewhere). */
class ExcursionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Excursion $excursion): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Excursion $excursion): bool
    {
        return $user->isStaff();
    }
}
