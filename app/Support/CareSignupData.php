<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareAnswer;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Ferienbetreuung sign-up sheet for one user: the periods still to come, the
 * children they may answer for, and who is already registered for which day.
 *
 * Two pages render it — „Ausflüge & Ferien" for parents and the staff-only /care —
 * so the shape lives here rather than in whichever controller needed it first.
 */
class CareSignupData
{
    /**
     * @param  bool  $ownChildrenOnly  „Ausflüge & Ferien" is a family page: an
     *                                 Erzieher:in with a child of their own answers for
     *                                 that child there, and for everybody else on /care.
     * @return array{children: array<int, array{id: int, name: string}>, periods: array<int, array<string, mixed>>, canOverrideDeadline: bool}
     */
    public static function for(User $user, bool $ownChildrenOnly = false): array
    {
        return self::build(
            $user,
            HolidayPeriod::query()
                ->care()
                ->with('careDays')
                ->whereDate('ends_on', '>=', Carbon::today())
                ->orderBy('starts_on')
                ->get(),
            $ownChildrenOnly,
        );
    }

    /**
     * The same sheet for a single Ferienbetreuung — the staff view on its own page,
     * where a period is set up and its roster filled in one place (as an Ausflug is).
     *
     * @return array{children: array<int, array{id: int, name: string}>, periods: array<int, array<string, mixed>>, canOverrideDeadline: bool}
     */
    public static function forPeriod(User $user, HolidayPeriod $period): array
    {
        return self::build($user, collect([$period->load('careDays')]), ownChildrenOnly: false);
    }

    /**
     * @param  Collection<int, HolidayPeriod>  $periods
     * @return array{children: array<int, array{id: int, name: string}>, periods: array<int, array<string, mixed>>, canOverrideDeadline: bool}
     */
    private static function build(User $user, Collection $periods, bool $ownChildrenOnly): array
    {
        // Only children enrolled somewhere in the offered range: one who left in the
        // summer has no business on the autumn sign-up sheet. Which of them belongs to
        // which period is decided per period below — the page shows several at once.
        $children = ($user->isStaff() && ! $ownChildrenOnly ? Child::query() : $user->children())
            ->when($periods->isNotEmpty(), fn ($q) => $q->activeBetween(
                $periods->min('starts_on'),
                $periods->max('ends_on'),
            ))
            ->orderBy('name')
            ->get(['children.id', 'children.name', 'children.active_from', 'children.active_until']);

        // Only the children shown need their sign-ups resolved.
        $registered = self::attendanceKeys($periods, $children->pluck('id')->all());
        $answered = HolidayCareAnswer::query()
            ->whereIn('holiday_period_id', $periods->pluck('id'))
            ->whereIn('child_id', $children->pluck('id'))
            ->get()
            ->map(fn (HolidayCareAnswer $a): string => $a->holiday_period_id.'|'.$a->child_id)
            ->flip();

        return [
            'children' => $children->map(fn (Child $c): array => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            'periods' => $periods->map(fn (HolidayPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'registration_deadline' => $period->registration_deadline?->toDateString(),
                'open' => $period->registrationIsOpen(),
                'note' => $period->note,
                'days' => $period->careDays->map(fn (HolidayCareDay $day): array => [
                    'id' => $day->id,
                    'date' => $day->date->toDateString(),
                    'starts_at' => HolidayCareDay::short($day->starts_at),
                    'ends_at' => HolidayCareDay::short($day->ends_at),
                    'activity' => $day->activity,
                    // Which of the listed children are signed up for this day.
                    'children' => $children->pluck('id')
                        ->filter(fn (int $id): bool => $registered->has($day->date->toDateString().'|'.$id))
                        ->values(),
                ])->values(),
                'answered' => $children->pluck('id')
                    ->filter(fn (int $id): bool => $answered->has($period->id.'|'.$id))
                    ->values(),
                // Enrolment is per period, not per page: a child who leaves in between
                // belongs on the earlier sign-up sheet but not the later one.
                'child_ids' => $children
                    ->filter(fn (Child $c): bool => self::enrolledDuring($c, $period))
                    ->pluck('id')->values(),
            ])->values()->all(),
            // Staff may still register someone once the deadline has passed.
            'canOverrideDeadline' => $user->isStaff(),
        ];
    }

    /** Whether the child is enrolled on at least one day of the period. */
    public static function enrolledDuring(Child $child, HolidayPeriod $period): bool
    {
        return ($child->active_from === null || $child->active_from->lte($period->ends_on))
            && ($child->active_until === null || $child->active_until->gte($period->starts_on));
    }

    /**
     * `date|childId` keys for the days these children are already signed up for —
     * i.e. the DailyDeparture rows that exist on the offered dates.
     *
     * @param  Collection<int, HolidayPeriod>  $periods
     * @param  list<int>  $childIds
     * @return Collection<string, int>
     */
    private static function attendanceKeys(Collection $periods, array $childIds): Collection
    {
        // Keyed off the care day, not the date: a plan override that happens to fall on
        // an offered day is not a sign-up, and showing it as one would tick a box the
        // family never ticked (and count them into the catering).
        return DailyDeparture::query()
            ->whereIn('holiday_care_day_id', $periods->flatMap->careDays->pluck('id'))
            ->whereIn('child_id', $childIds)
            ->get()
            ->map(fn (DailyDeparture $d): string => $d->date->toDateString().'|'.$d->child_id)
            ->flip();
    }
}
