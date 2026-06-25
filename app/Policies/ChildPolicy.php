<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\User;

class ChildPolicy
{
    /** Reading is open to every authenticated user (open information policy). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Child $child): bool
    {
        return true;
    }

    /** Only staff add new children. */
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /** Staff may edit any child; a parent may edit their own child(ren). */
    public function update(User $user, Child $child): bool
    {
        return $user->isStaff() || $child->isGuardedBy($user);
    }

    /** Only staff delete children. */
    public function delete(User $user, Child $child): bool
    {
        return $user->isStaff();
    }

    /** Only staff manage which parents are linked to a child. */
    public function manageGuardians(User $user, Child $child): bool
    {
        return $user->isStaff();
    }
}
