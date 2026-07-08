<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DepartureMethod;
use App\Models\DailyDeparture;
use App\Models\User;

/**
 * Parent-facing summary of „geht mit … mit" arrangements touching a user's children —
 * shown prominently above the Wochenplan and Heute views so families see, at a glance,
 * that their child is going home with another (and the confirmation status), or that
 * they will be taking an extra child home. Teachers get the in-plan display instead.
 */
class CompanionNotes
{
    /**
     * @param  array<int, string>  $dates
     * @return array<int, array{id:int, day:string, child:string, companion:string, status:string, actionable:bool}>
     */
    public static function for(User $user, array $dates): array
    {
        if ($user->isStaff() || empty($dates)) {
            return [];
        }

        $myChildIds = $user->children()->pluck('children.id');
        if ($myChildIds->isEmpty()) {
            return [];
        }

        $arrangements = DailyDeparture::query()
            ->where('planned_method', DepartureMethod::WithChild)
            ->whereNotNull('companion_child_id')
            ->whereIn('date', $dates)
            ->where(fn ($q) => $q
                ->whereIn('child_id', $myChildIds)
                ->orWhereIn('companion_child_id', $myChildIds))
            ->with(['child:id,name', 'companion:id,name'])
            ->orderBy('date')
            ->get();

        return $arrangements->map(function (DailyDeparture $d) use ($myChildIds) {
            // Confirmation is only needed when the companion goes home alone; otherwise
            // both are picked up by the companion's family.
            $required = EffectivePlan::for($d->companion_child_id, $d->date->toDateString())['method'] === DepartureMethod::SentHome->value;

            $status = match (true) {
                ! $required => 'pickup',
                $d->companion_confirmed === true => 'confirmed',
                $d->companion_confirmed === false => 'declined',
                default => 'pending',
            };

            return [
                'id' => $d->id,
                'day' => $d->date->locale(app()->getLocale())->isoFormat('dd, D.M.'),
                'child' => $d->child->name,          // the tag-along
                'companion' => $d->companion->name,  // the child being gone-home-with
                'status' => $status,
                // The companion's own guardian is the one who must still confirm.
                'actionable' => $status === 'pending' && $myChildIds->contains($d->companion_child_id),
            ];
        })->all();
    }
}
