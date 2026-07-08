<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Absence;
use App\Support\CompanionReconciler;

class AbsenceObserver
{
    /**
     * When a child is newly reported away, no one can go home with them — unwind any
     * „geht mit … mit" arrangements that named them as the companion (see the reconciler).
     * A child can't be set to go with an already-absent companion, so `created` is enough.
     */
    public function created(Absence $absence): void
    {
        CompanionReconciler::companionAbsent($absence->child_id, $absence->date->toDateString());
    }
}
