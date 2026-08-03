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
use App\Support\CareSignupData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Parents sign their children up for individual Ferienbetreuung days; staff may do
 * it for anyone, including after the Anmeldeschluss (someone always asks late).
 */
class HolidayCareRegistrationController extends Controller
{
    /**
     * There is no sign-up page of its own any more: a family answers on
     * „Ausflüge & Ferien" with everything else that wants an answer, and staff fill a
     * roster on the Ferienbetreuung's own page — as they do for an Ausflug. The route
     * survives as a signpost, because Slack messages and bookmarks point at it.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->isStaff() ? 'closures.index' : 'polls.index');
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

        // A child who isn't enrolled over these dates can't attend them.
        abort_unless(CareSignupData::enrolledDuring($child, $period), 403);

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

        // Days that are over can't be registered for any more — saving an earlier day
        // of a running Ferienbetreuung must not write a plan into the past.
        if ($day->date->lt(Carbon::today())) {
            return;
        }

        $departure = DailyDeparture::firstOrNew([
            'child_id' => $child->id,
            'date' => $day->date->toDateString(),
        ]);

        if ($departure->exists) {
            // A plan entered before the Ferienbetreuung existed (someone set a pickup
            // for that date in the Wochenplan) is adopted as the sign-up: the family is
            // saying the child comes, and the row already says when they leave. Without
            // this the tick has no effect at all — the box comes back empty on every
            // save, because a sign-up is recognised by the care day it names.
            if ($departure->holiday_care_day_id === null) {
                $departure->update(['holiday_care_day_id' => $day->id]);
            }

            return; // Never overwrite a time the family chose.
        }

        $departure->fill([
            'holiday_care_day_id' => $day->id,
            'planned_time' => $day->ends_at,
            'planned_method' => $this->defaultMethod($child, $day),
            'status' => DepartureStatus::Present,
        ])->save();
    }

    /**
     * Withdraw — but only a row that is this day's sign-up, and only while the day is
     * still ahead. An override the family entered before the Ferienbetreuung existed
     * isn't ours to delete, and a day already under way belongs to the board.
     */
    private function withdraw(HolidayCareDay $day, Child $child): void
    {
        // A day already under way belongs to the board: staff mark children off there,
        // and deleting the row mid-morning would take a child who is standing in the
        // Hort off the roster with nobody the wiser. „Kommt heute nicht" is a
        // Krankmeldung, not a withdrawal.
        if ($day->date->lte(Carbon::today())) {
            return;
        }

        DailyDeparture::where('child_id', $child->id)
            ->where('holiday_care_day_id', $day->id)
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
}
