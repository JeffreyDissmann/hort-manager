<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Child;
use App\Support\CompanionReconciler;

class ChildObserver
{
    /**
     * Before a child is deleted, unwind any „geht mit … mit" arrangements that named
     * them as the companion — otherwise those dependents would be left pointing at a
     * child who no longer exists (the FK only nulls the link, silently stranding them).
     */
    public function deleting(Child $child): void
    {
        CompanionReconciler::companionRemoved($child);
    }
}
