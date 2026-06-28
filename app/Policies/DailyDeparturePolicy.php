<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DailyDeparture;
use App\Models\User;

class DailyDeparturePolicy
{
    /** Only staff record (or undo) a departure on the board. */
    public function mark(User $user, DailyDeparture $departure): bool
    {
        return $user->isStaff();
    }
}
