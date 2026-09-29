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
        $this->syncExcursionInvites($child);
    }

    /**
     * The enrolment period is what decides who is invited, so moving it has to move the
     * invitations with it — the mirror of created(). A child who leaves is withdrawn
     * from the trips ahead of them (they can't come, and their family kept being asked
     * to answer), one whose leaving date moves out again is invited back.
     *
     * Only trips from today on: a past trip is a record of who was there, and a child
     * enrolled at the time stays on it whatever happens to their period afterwards.
     */
    public function updated(Child $child): void
    {
        if ($child->wasChanged(['active_from', 'active_until'])) {
            $this->syncExcursionInvites($child);
        }
    }

    /**
     * Invite this child to every upcoming trip they are enrolled for, and withdraw them
     * from the ones they are not.
     */
    private function syncExcursionInvites(Child $child): void
    {
        Excursion::query()
            ->whereDate('date', '>=', Carbon::today())
            ->get()
            ->each(function (Excursion $excursion) use ($child): void {
                $child->isActiveOn($excursion->date)
                    ? $excursion->children()->syncWithoutDetaching([$child->id])
                    : $excursion->children()->detach($child->id);
            });
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
