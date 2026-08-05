<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Child;
use App\Models\Excursion;
use App\Support\CompanionReconciler;
use Illuminate\Support\Carbon;

class ChildObserver
{
    /**
     * An Ausflug invites every child that exists when it is created. A child who joins
     * the Hort afterwards was simply missing from the list — the family saw the trip
     * without their own child on it („2 von 5 dabei") and had no way to answer, until
     * staff happened to re-save the trip. So a new child is invited to every upcoming
     * trip they are enrolled for.
     *
     * No DM goes out for this: at this moment the child usually has no guardians yet
     * (they are linked afterwards). The pending poll shows up in the app's banner and
     * tab badge, and `excursions:remind-rsvps` chases it like any other open answer.
     */
    public function created(Child $child): void
    {
        Excursion::query()
            ->whereDate('date', '>=', Carbon::today())
            ->get()
            ->filter(fn (Excursion $excursion): bool => $child->isActiveOn($excursion->date))
            ->each(fn (Excursion $excursion) => $excursion->children()->syncWithoutDetaching([$child->id]));
    }

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
