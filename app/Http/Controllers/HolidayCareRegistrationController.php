<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareAnswer;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Parents sign their children up for individual Ferienbetreuung days; staff may do
 * it for anyone, including after the Anmeldeschluss (someone always asks late).
 */
class HolidayCareRegistrationController extends Controller
{
    /** The opt-in screen: every open Ferienbetreuung × the children the user manages. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $children = $user->isStaff()
            ? Child::query()->orderBy('name')->get(['id', 'name'])
            : $user->children()->orderBy('name')->get(['children.id', 'children.name']);

        $periods = HolidayPeriod::query()
            ->care()
            ->with('careDays')
            ->whereDate('ends_on', '>=', Carbon::today())
            ->orderBy('starts_on')
            ->get();

        // Only the children shown need their sign-ups resolved.
        $registered = $this->attendanceKeys($periods, $children->pluck('id')->all());
        $answered = HolidayCareAnswer::query()
            ->whereIn('holiday_period_id', $periods->pluck('id'))
            ->whereIn('child_id', $children->pluck('id'))
            ->get()
            ->map(fn (HolidayCareAnswer $a): string => $a->holiday_period_id.'|'.$a->child_id)
            ->flip();

        return Inertia::render('Care/Index', [
            'children' => $children->map(fn (Child $c): array => ['id' => $c->id, 'name' => $c->name])->values(),
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
            ])->values(),
            // Staff may still register someone once the deadline has passed.
            'canOverrideDeadline' => $user->isStaff(),
        ]);
    }

    /** Save one child's days for one Ferienbetreuung — the full set, not a diff. */
    public function update(Request $request, HolidayPeriod $period): RedirectResponse
    {
        abort_unless($period->isCare(), 404);

        $validated = $request->validate([
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'day_ids' => ['present', 'array'],
            'day_ids.*' => ['integer'],
        ]);

        $child = Child::findOrFail($validated['child_id']);
        // Same rule as editing the child's plan: staff, or this child's guardian.
        $this->authorize('update', $child);

        $user = $request->user();
        abort_unless($period->registrationIsOpen() || $user->isStaff(), 403);

        // Ignore ids from another period — the days a period offers are the only
        // ones it can register anyone for.
        $wanted = $period->careDays()->whereIn('id', $validated['day_ids'])->pluck('id');

        foreach ($period->careDays as $day) {
            $wanted->contains($day->id)
                ? $this->attend($day, $child, $user)
                : $this->withdraw($day, $child);
        }

        // Picking no days is a real answer, so record it either way.
        HolidayCareAnswer::updateOrCreate(
            ['holiday_period_id' => $period->id, 'child_id' => $child->id],
            ['answered_by' => $user->id, 'answered_at' => now()],
        );

        return back()->with('status', __('flash.care_registered', ['name' => $child->name]));
    }

    /**
     * Sign a child up: the DailyDeparture for that date *is* the registration, so the
     * day is planned like any other — end of the Betreuungszeit, and the method the
     * family normally uses (going home alone is a property of the child, not the term).
     */
    private function attend(HolidayCareDay $day, Child $child, User $user): void
    {
        // A Schließzeit entered since the page loaded un-offers the day; signing up
        // then would plan a child for a day the board refuses to show.
        if (HolidayPeriod::closesOn($day->date)) {
            return;
        }

        $departure = DailyDeparture::firstOrNew([
            'child_id' => $child->id,
            'date' => $day->date->toDateString(),
        ]);

        if ($departure->exists) {
            return; // Already signed up — never overwrite a plan the family adjusted.
        }

        $departure->fill([
            'planned_time' => $day->ends_at,
            'planned_method' => $this->defaultMethod($child, $day),
            'status' => DepartureStatus::Present,
        ])->save();
    }

    /** Withdraw, unless the day already happened — history stays as it was. */
    private function withdraw(HolidayCareDay $day, Child $child): void
    {
        DailyDeparture::where('child_id', $child->id)
            ->whereDate('date', $day->date)
            ->whereNull('left_at')
            ->delete();
    }

    /**
     * The Stammplan's method for that weekday, else any method the child has, else
     * „wird abgeholt" — a child with no Stammplan at all has nothing to inherit.
     */
    private function defaultMethod(Child $child, HolidayCareDay $day): DepartureMethod
    {
        $schedules = $child->weeklySchedules;

        $method = $schedules->firstWhere('weekday', $day->date->dayOfWeekIso)?->method
            ?? $schedules->firstWhere(fn ($s): bool => $s->method !== null)?->method;

        // „Geht mit … mit" mirrors another child and can't be a default.
        return $method === null || $method === DepartureMethod::WithChild
            ? DepartureMethod::PickedUp
            : $method;
    }

    /**
     * `date|childId` keys for the days these children are already signed up for —
     * i.e. the DailyDeparture rows that exist on the offered dates.
     *
     * @param  Collection<int, HolidayPeriod>  $periods
     * @param  list<int>  $childIds
     * @return Collection<string, int>
     */
    private function attendanceKeys(Collection $periods, array $childIds): Collection
    {
        $dates = $periods->flatMap->careDays->map(
            fn (HolidayCareDay $day): string => $day->date->toDateString(),
        );

        return DailyDeparture::query()
            ->whereIn('date', $dates)
            ->whereIn('child_id', $childIds)
            ->get()
            ->map(fn (DailyDeparture $d): string => $d->date->toDateString().'|'.$d->child_id)
            ->flip();
    }
}
