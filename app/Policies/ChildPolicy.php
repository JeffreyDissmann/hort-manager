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

    /** Anyone may add a child (parents self-serve; the creator becomes a guardian). */
    public function create(User $user): bool
    {
        return true;
    }

    /** Staff may edit any child; a parent may edit their own child(ren). */
    public function update(User $user, Child $child): bool
    {
        return $user->isStaff() || $child->isGuardedBy($user);
    }

    /** Staff, or a guardian of the child, may delete it. */
    public function delete(User $user, Child $child): bool
    {
        return $user->isStaff() || $child->isGuardedBy($user);
    }

    /** Staff, or a guardian of the child, manage which parents are linked to it. */
    public function manageGuardians(User $user, Child $child): bool
    {
        return $user->isStaff() || $child->isGuardedBy($user);
    }
}
