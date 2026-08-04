<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HolidayPeriodType;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\Setting;
use App\Rules\NoExcursionInRange;
use App\Support\CareSignupData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Staff manage the Hort's Schließzeiten; everyone can see them. */
class HolidayPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HolidayPeriod::class);

        $today = Carbon::today()->toDateString();

        $periods = HolidayPeriod::query()
            ->closed()
            ->orderBy('starts_on')
            ->get()
            ->map(fn (HolidayPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'note' => $period->note,
                'days' => $period->dayCount(),
            ]);

        // Ferienbetreuung: the offered days carry the times, so summarise them here.
        $care = HolidayPeriod::query()
            ->care()
            ->with('careDays')
            ->orderBy('starts_on')
            ->get()
            ->map(fn (HolidayPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'registration_deadline' => $period->registration_deadline?->toDateString(),
                'registration_open' => $period->registrationIsOpen(),
                'note' => $period->note,
                'day_count' => $period->careDays->count(),
                'days' => $period->careDays->map(fn (HolidayCareDay $day): array => [
                    'id' => $day->id,
                    'date' => $day->date->toDateString(),
                    'starts_at' => HolidayCareDay::short($day->starts_at),
                    'ends_at' => HolidayCareDay::short($day->ends_at),
                ])->values(),
                // Days staff stopped offering — shown so removing one isn't final.
                'removed_days' => $period->careDays()->onlyTrashed()->orderBy('date')->get()
                    ->map(fn (HolidayCareDay $day): array => [
                        'id' => $day->id,
                        'date' => $day->date->toDateString(),
                    ])->values(),
            ]);

        [$careStart, $careEnd] = Setting::careDefaultWindow();

        return Inertia::render('Closures/Index', [
            // A period counts as over only once its last day has passed.
            'upcoming' => $periods->filter(fn (array $p): bool => $p['ends_on'] >= $today)->values(),
            'past' => $periods->filter(fn (array $p): bool => $p['ends_on'] < $today)
                ->sortByDesc('starts_on')->values(),
            'care' => $care->filter(fn (array $p): bool => $p['ends_on'] >= $today)->values(),
            'careDefaults' => ['starts_at' => $careStart, 'ends_at' => $careEnd],
            'canManage' => $request->user()->can('create', HolidayPeriod::class),
        ]);
    }

    /**
     * One Ferienbetreuung on its own page — the days it offers and who is signed up,
     * the way an Ausflug carries its own fields and its answers. Setting a period up
     * and filling its roster is one job, so it is one screen.
     */
    public function edit(Request $request, HolidayPeriod $closure): Response
    {
        $this->authorize('update', $closure);

        $closure->load('careDays');

        return Inertia::render('Closures/Edit', [
            'period' => [
                'id' => $closure->id,
                'name' => $closure->name,
                'type' => $closure->type->value,
                'starts_on' => $closure->starts_on->toDateString(),
                'ends_on' => $closure->ends_on->toDateString(),
                'registration_deadline' => $closure->registration_deadline?->toDateString(),
                'registration_open' => $closure->registrationIsOpen(),
                'note' => $closure->note,
                'days' => $closure->careDays->map(fn (HolidayCareDay $day): array => [
                    'id' => $day->id,
                    'date' => $day->date->toDateString(),
                    'starts_at' => HolidayCareDay::short($day->starts_at),
                    'ends_at' => HolidayCareDay::short($day->ends_at),
                ])->values(),
                // Days staff stopped offering — shown so removing one isn't final.
                'removed_days' => $closure->careDays()->onlyTrashed()->orderBy('date')->get()
                    ->map(fn (HolidayCareDay $day): array => [
                        'id' => $day->id,
                        'date' => $day->date->toDateString(),
                    ])->values(),
            ],
            // The roster: every child, every offered day — empty for a Schließzeit.
            'roster' => $closure->isCare()
                ? CareSignupData::forPeriod($request->user(), $closure)
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', HolidayPeriod::class);

        $period = HolidayPeriod::create($this->validated($request));

        // A Ferienbetreuung offers every weekday of its range, from the default times.
        if ($period->isCare()) {
            $period->generateCareDays();
        }

        return back()->with('status', __('flash.closure_saved'));
    }

    public function update(Request $request, HolidayPeriod $closure): RedirectResponse
    {
        $this->authorize('update', $closure);

        $validated = $this->validated($request, $closure);

        // The type is fixed once created. Flipping a Ferienbetreuung to „geschlossen"
        // would un-offer every day and delete every family's sign-up with it — from a
        // segmented toggle at the top of an edit form, with no confirmation, and with
        // nothing to restore afterwards. Converting is also meaningless: the two kinds
        // share nothing but a date range. Delete and re-create instead.
        unset($validated['type']);

        $closure->update($validated);

        // Extending the range offers the new weekdays; days already edited are left
        // alone, and days now outside the range are dropped. Deleted per model, not
        // by query: the observer withdrawing their sign-ups runs on the model event.
        // Force-deleted, because a day is only outside the range until someone moves
        // the range back — that's not the same as staff un-offering it.
        if ($closure->isCare()) {
            $closure->careDays()
                ->withTrashed()
                ->where(fn ($q) => $q->whereDate('date', '<', $closure->starts_on)
                    ->orWhereDate('date', '>', $closure->ends_on))
                ->get()
                ->each->forceDelete();

            $closure->refresh()->generateCareDays();
        }

        return back()->with('status', __('flash.closure_saved'));
    }

    /** Adjust one offered day's Betreuungszeit (its content lives on /program). */
    public function updateCareDay(Request $request, HolidayCareDay $careDay): RedirectResponse
    {
        $this->authorize('update', $careDay->period);

        $validated = $request->validate([
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ]);

        $previousEnd = $careDay->ends_at;

        $careDay->update($validated);

        // A sign-up is planned for the end of the Betreuungszeit, so moving the day's
        // end has to move them with it — otherwise everyone who registered before the
        // change keeps a pickup time the Hort no longer offers. Families who set their
        // own time are left alone: that plan is deliberate. Saved per model so the
        // change lands in the Protokoll like any other plan change.
        if ($careDay->ends_at !== $previousEnd) {
            $careDay->departures()
                ->whereNull('left_at')
                ->where('planned_time', $previousEnd)
                ->get()
                ->each->update(['planned_time' => $careDay->ends_at]);
        }

        return back()->with('status', __('flash.care_day_saved'));
    }

    /** Stop offering a single day (a Betriebsausflug mid-Ferienbetreuung, say). */
    public function destroyCareDay(Request $request, HolidayCareDay $careDay): RedirectResponse
    {
        $this->authorize('update', $careDay->period);

        // Registrations for that day go with it (see HolidayCareDayObserver) — nobody
        // can attend a day that isn't offered any more. Soft-deleted: the tombstone is
        // what stops the next save of the period from offering the day again.
        $careDay->delete();

        return back()->with('status', __('flash.care_day_removed'));
    }

    /** Offer a removed day again — the sign-ups it had are not restored with it. */
    public function restoreCareDay(Request $request, HolidayCareDay $careDay): RedirectResponse
    {
        $this->authorize('update', $careDay->period);

        abort_if(HolidayPeriod::closesOn($careDay->date), 403, 'An diesem Tag ist der Hort geschlossen.');

        $careDay->restore();

        return back()->with('status', __('flash.care_day_restored'));
    }

    public function destroy(Request $request, HolidayPeriod $closure): RedirectResponse
    {
        $this->authorize('delete', $closure);

        $closure->delete();

        return back()->with('status', __('flash.closure_deleted'));
    }

    /**
     * @param  HolidayPeriod|null  $period  the period being edited, whose type is fixed
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?HolidayPeriod $period = null): array
    {
        // The type the period will have (it can't be changed on edit).
        $type = $period?->type->value ?? $request->input('type') ?? HolidayPeriodType::Closed->value;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(HolidayPeriodType::class)],
            'starts_on' => ['required', 'date'],
            // A single closed day is starts_on == ends_on, so equality is allowed.
            // Neither kind of Zeitraum may swallow an Ausflug: a closed day has nobody
            // to go, and during a Ferienbetreuung the outing *is* the day's Aktivität.
            'ends_on' => [
                'required',
                'date',
                'after_or_equal:starts_on',
                new NoExcursionInRange($request->input('starts_on')),
            ],
            // Opting in after the Ferienbetreuung has started makes no sense.
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // On edit the stored type wins (it can't be changed); on create, default to a
        // Schließzeit, which is what the form opens on.
        $validated['type'] = $type;

        // Only a Ferienbetreuung has anything to register for.
        if ($validated['type'] !== HolidayPeriodType::Care->value) {
            $validated['registration_deadline'] = null;
        }

        return $validated;
    }
}
