<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A child's pickup on an excursion day. A pickup between departure and return means
 * nobody is at the Hort to hand the child over — this reports that clash and moves the
 * pickup to the trip's return when asked (the app) or on a Slack „Ja" (automatic).
 *
 * The move is always that one day only: it writes the day's DailyDeparture, never the
 * Stammplan, and keeps everything else about the day (note, „kommt später", Art).
 */
class ExcursionPickup
{
    /**
     * The child's pickup for the trip's date, or null when there is no plan to judge.
     *
     * @return array{time: ?string, method: ?string, conflict: bool, movable: bool}|null
     */
    public static function state(Excursion $excursion, Child $child): ?array
    {
        $date = $excursion->date->toDateString();
        $plan = EffectivePlan::for($child->id, $date);

        // Away that day → not going anyway, so the day's plan says nothing.
        if (Absence::query()->where('child_id', $child->id)->where('date', $date)->exists()) {
            return null;
        }

        // „geht mit … mit" mirrors the companion's time: the clash is real, but moving
        // it would silently break that arrangement, so it is only ever reported.
        $isCompanion = $plan['method'] === DepartureMethod::WithChild->value && $plan['companion_child_id'];
        $time = $isCompanion
            ? (EffectivePlan::for($plan['companion_child_id'], $date)['time'] ?? null)
            : $plan['time'];

        if ($plan['method'] === null && $time === null) {
            return null;
        }

        $return = self::short($excursion->return_at);
        $depart = self::short($excursion->depart_at) ?? '00:00';
        $departed = DailyDeparture::query()
            ->where('child_id', $child->id)->where('date', $date)
            ->where('status', '!=', DepartureStatus::Present)->exists();

        return [
            'time' => $time,
            'method' => $plan['method'],
            'conflict' => $time !== null && $return !== null && $time >= $depart && $time < $return,
            'movable' => $return !== null
                && ! $isCompanion
                && ! $departed
                && $date >= Carbon::today()->toDateString()
                && ! HolidayPeriod::closesOn($date),
        ];
    }

    /**
     * Move the clashing pickup to the trip's return time, for that date only. Returns
     * the previous time when something actually moved, null when there was nothing to
     * do (no clash, a companion pickup, a past day, …).
     */
    public static function moveToReturn(Excursion $excursion, Child $child, User $actor): ?string
    {
        $state = self::state($excursion, $child);

        if ($state === null || ! $state['conflict'] || ! $state['movable']) {
            return null;
        }

        $date = $excursion->date->toDateString();
        $return = self::short($excursion->return_at);

        $departure = DailyDeparture::firstOrNew(['child_id' => $child->id, 'date' => $date]);
        $before = $state['time'];

        if (! $departure->exists) {
            $departure->status = DepartureStatus::Present;
            // A day that still follows the Stammplan becomes an override — carrying the
            // plan over, so only the time changes.
            $departure->planned_method = $state['method'];
            $departure->time_qualifier = EffectivePlan::for($child->id, $date)['qualifier'];
        }

        $departure->planned_time = $return;
        $departure->save();

        activity()
            ->causedBy($actor)
            ->performedOn($departure)
            ->event('adjusted')
            ->withChanges(['attributes' => ['planned_time' => $return], 'old' => ['planned_time' => $before]])
            ->log($child->name.' · '.$date);

        // Another child may be tagging along with this one — re-check those.
        CompanionReconciler::reconcile($child->id, $date);

        LateChange::notify($actor, $child, $date, "Abholung auf {$return} verschoben (Ausflug „{$excursion->name}\")");

        return $before;
    }

    private static function short(mixed $time): ?string
    {
        return $time ? substr((string) $time, 0, 5) : null;
    }
}
