<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DepartureMethod;
use App\Jobs\AskCompanionConfirmation;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Notifications\CompanionCancelled;
use App\Notifications\CompanionRequest;
use App\Services\SlackCompanion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Keeps „geht mit … mit" arrangements honest when the *companion's* own plan changes.
 *
 * Confirmation is only required while the companion goes home alone. Because we store
 * the answer (rather than recompute it everywhere), a change to the companion's plan
 * can leave a stale state — e.g. a child auto-approved while the companion was being
 * picked up, who then switches to going home alone. This re-opens such arrangements
 * (and asks the family again), or auto-clears the gate when it's no longer needed.
 *
 * System (auto) approvals are marked by a null `companion_confirmed_by`; a real answer
 * from a guardian/staff sets the confirmer and is never overridden here.
 */
class CompanionReconciler
{
    /**
     * Bring the arrangements that name this child up to date with their new plan.
     *
     * Returns one German sentence per arrangement that was actually touched, so the
     * person who made the change is told what it did to another family — until now the
     * only sign was a DM to *them*, and the editor's own screen said nothing.
     *
     * @return list<string>
     */
    public static function reconcile(int $companionChildId, string $date): array
    {
        $companion = Child::find($companionChildId);
        if ($companion === null) {
            return [];
        }

        $dependents = $companion->accompaniedDepartures()
            ->where('date', $date)
            ->with('child.guardians')
            ->get();

        if ($dependents->isEmpty()) {
            return [];
        }

        $plan = EffectivePlan::for($companionChildId, $date);

        // The companion can only be a companion if they themselves leave on their own —
        // a concrete pickup that is picked_up or sent_home. If their plan changed so that
        // is no longer true (they now tag along with someone else → a chain, or they no
        // longer have a pickup), nobody can go with them: unwind, same as an absence.
        $leavesOnOwn = $plan['time'] !== null
            && in_array($plan['method'], [DepartureMethod::PickedUp->value, DepartureMethod::SentHome->value], true);

        if (! $leavesOnOwn) {
            return self::unwind($companion, $dependents, $date);
        }

        $companionAlone = $plan['method'] === DepartureMethod::SentHome->value;
        $guardians = $companion->guardians()->get();
        $notes = [];

        foreach ($dependents as $dependent) {
            $answeredByHuman = $dependent->companion_confirmed_by !== null;

            if ($companionAlone) {
                // Companion now goes home alone. Reopen a stale auto-approval and ask
                // the family; a human answer stands, and an already-pending one was
                // asked at creation, so neither is touched (no repeat notifications).
                if (! $answeredByHuman && $dependent->companion_confirmed === true) {
                    $dependent->update(['companion_confirmed' => null]);
                    Notification::send($guardians, new CompanionRequest($dependent));
                    AskCompanionConfirmation::dispatch($dependent);

                    $notes[] = __('flash.companion_reconfirm', [
                        'name' => $dependent->child->name,
                        'companion' => $companion->name,
                    ]);
                }
            } elseif (! $answeredByHuman && $dependent->companion_confirmed === null) {
                // Companion is picked up again → an adult is there, no gate needed.
                $dependent->update(['companion_confirmed' => true]);
            }
        }

        return $notes;
    }

    /**
     * The companion was reported away, so nobody can go home with them. Same procedure
     * as when the companion can no longer be one (see reconcile): unwind each dependent.
     *
     * @return list<string>
     */
    public static function companionAbsent(int $companionChildId, string $date): array
    {
        $companion = Child::find($companionChildId);
        if ($companion === null) {
            return [];
        }

        $dependents = $companion->accompaniedDepartures()
            ->where('date', $date)
            ->with('child.guardians')
            ->get();

        return self::unwind($companion, $dependents, $date);
    }

    /**
     * The companion child is being deleted → unwind every arrangement (any day) that
     * named them, so no dependent is left pointing at a child who no longer exists.
     */
    public static function companionRemoved(Child $companion): void
    {
        $dependents = $companion->accompaniedDepartures()->with('child.guardians')->get();

        foreach ($dependents as $dependent) {
            $child = $dependent->child;
            $guardians = $child->guardians;
            $shortDate = $dependent->date->format('d.m.');

            SlackCompanion::cancelFor($dependent, $child->name, $companion->name);
            self::revert($dependent);

            Notification::send($guardians, new CompanionCancelled($child->name, $companion->name, $shortDate));
        }
    }

    /**
     * Revert each dependent arrangement to that child's Stammplan (a safe default) and
     * tell their family to plan a fresh pickup — otherwise a child could be left without
     * a way home.
     *
     * @param  Collection<int, DailyDeparture>  $dependents
     * @return list<string>
     */
    private static function unwind(Child $companion, $dependents, string $date): array
    {
        $shortDate = Carbon::parse($date)->format('d.m.');
        $notes = [];

        foreach ($dependents as $dependent) {
            $child = $dependent->child;
            $guardians = $child->guardians; // eager-loaded before the row is deleted

            SlackCompanion::cancelFor($dependent, $child->name, $companion->name);
            self::revert($dependent);

            Notification::send($guardians, new CompanionCancelled($child->name, $companion->name, $shortDate));

            $notes[] = __('flash.companion_unwound', [
                'name' => $child->name,
                'companion' => $companion->name,
            ]);
        }

        return $notes;
    }

    /**
     * Drop the arrangement itself. Normally that means deleting the row, which puts the
     * day back on the child's Stammplan.
     *
     * On a **Ferienbetreuung** day the row is not an override but the *sign-up itself*
     * (there is no separate registration table), and there is no Stammplan behind it —
     * deleting it would quietly take the child off the roster, possibly long after the
     * Anmeldeschluss. So the sign-up stays and only the companion part is undone: back
     * to the end of the Betreuungszeit and the method the child normally uses, exactly
     * as signing up would have planned the day.
     */
    private static function revert(DailyDeparture $dependent): void
    {
        $careDay = $dependent->careDay;

        if ($careDay === null) {
            $dependent->delete();

            return;
        }

        $dependent->update([
            'planned_time' => $careDay->ends_at,
            'planned_method' => $careDay->defaultMethodFor($dependent->child),
            'time_qualifier' => null,
            'companion_child_id' => null,
            'companion_confirmed' => null,
            'companion_confirmed_by' => null,
            'companion_confirmed_at' => null,
        ]);
    }
}
