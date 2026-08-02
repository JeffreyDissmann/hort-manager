<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Http\Requests\AdjustDayRequest;
use App\Jobs\AskCompanionConfirmation;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Notifications\CompanionRequest;
use App\Support\CompanionReconciler;
use App\Support\EffectivePlan;
use App\Support\LateChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class WeeklyAdjustmentController extends Controller
{
    /** Set an individual adjustment for one child on one day of the current week. */
    public function update(AdjustDayRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $child = Child::findOrFail($validated['child_id']);
        $departure = $this->authorizedDeparture($child, $validated['date']);
        $method = $validated['planned_method'] ?? null;

        // Companion fields reset by default; only the with_child method sets them.
        $fields = [
            'planned_time' => $validated['planned_time'] ?? null,
            // The qualifier only qualifies a "geht allein" time.
            'time_qualifier' => $method === DepartureMethod::SentHome->value ? ($validated['time_qualifier'] ?? null) : null,
            'planned_method' => $method,
            'companion_child_id' => null,
            'companion_confirmed' => null,
            'companion_confirmed_by' => null,
            'companion_confirmed_at' => null,
            'note' => $validated['note'] ?? null,
        ];

        if ($method === DepartureMethod::WithChild->value) {
            // Availability + no-chains are validated in AdjustDayRequest.
            $companionId = (int) $validated['companion_child_id'];

            // Confirmation is only needed when the companion themselves goes home alone
            // (otherwise an adult is there to take responsibility). The time is mirrored
            // from the companion, so it isn't stored on this row.
            $needsConfirmation = EffectivePlan::for($companionId, $validated['date'])['method'] === DepartureMethod::SentHome->value;

            $fields['planned_time'] = null;
            $fields['time_qualifier'] = null;
            $fields['companion_child_id'] = $companionId;
            // Auto-approved (companion is picked up) rows keep a null confirmer, marking
            // them as *system*-approved so the reconciler can reopen them if the companion
            // later switches to going home alone.
            $fields['companion_confirmed'] = $needsConfirmation ? null : true;
            $fields['companion_confirmed_by'] = null;
            $fields['companion_confirmed_at'] = $needsConfirmation ? null : now();
        }

        // Snapshot the plan before the change (save() re-syncs the original, so we
        // can't read the old values afterwards).
        $before = $this->planSnapshot($departure);

        $departure->fill($fields);

        if (! $departure->exists) {
            $departure->status = DepartureStatus::Present;
        }

        $departure->save();

        // Manual activity() logging always writes a row (Spatie's dontLogEmptyChanges()
        // only applies to the model-event path), so guard it ourselves: skip when the
        // plan the user sees didn't change — re-saving an identical plan is a common
        // DayEditor no-op that would otherwise crowd the Protokoll with empty entries.
        // Keyed off the display diff, not $departure->wasChanged(), on purpose: a
        // with_child re-save bumps companion_confirmed_at without changing the plan.
        $changes = $this->diff($before, $this->planSnapshot($departure));

        if ($changes['attributes'] !== []) {
            activity()
                ->causedBy($request->user())
                ->performedOn($departure)
                ->event('adjusted')
                ->withChanges($changes)
                ->log($child->name.' · '.$validated['date']);
        }

        // Ask this row's companion family, if their confirmation is now pending.
        if ($departure->awaitingCompanionConfirmation()) {
            Notification::send($departure->companion->guardians()->get(), new CompanionRequest($departure));
            AskCompanionConfirmation::dispatch($departure);
        }

        // This child may itself be someone else's companion — re-evaluate those
        // arrangements against the plan we just saved (e.g. picked-up → goes alone).
        CompanionReconciler::reconcile($child->id, $validated['date']);

        // A late same-day change staff need to know about (no-op otherwise). Only
        // when the plan really moved — a re-saved identical plan isn't news.
        if ($changes['attributes'] !== []) {
            LateChange::notify(
                $request->user(),
                $child,
                $validated['date'],
                LateChange::describePlan($departure),
            );
        }

        return back()->with('status', __('flash.plan_updated', ['name' => $child->name]));
    }

    /** Revert one day back to the standard Stammplan. */
    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'date' => ['required', 'date'],
        ]);

        $child = Child::findOrFail($validated['child_id']);
        $departure = $this->authorizedDeparture($child, $validated['date']);

        // On a Ferienbetreuung day there is no Stammplan to fall back to, so „reset"
        // would simply cancel the child's place — past the Anmeldeschluss, where /care
        // refuses exactly that, and with nothing to sign them back up with. Giving up
        // a place is a decision for the sign-up screen, not a side effect of an
        // „Auf Standard" button.
        abort_if(
            $departure->isCareRegistration(),
            403,
            'Ein Ferienbetreuungstag wird über die Anmeldung abgemeldet, nicht zurückgesetzt.',
        );

        // Deleting the override row makes the board fall back to the Stammplan.
        if ($departure->exists) {
            activity()
                ->causedBy($request->user())
                ->performedOn($departure)
                ->event('reset')
                ->log($child->name.' · '.$validated['date']);

            $departure->delete();

            LateChange::notify(
                $request->user(),
                $child,
                $validated['date'],
                'zurück auf den Stammplan gesetzt',
            );
        }

        return back()->with('status', __('flash.day_reset', ['name' => $child->name]));
    }

    /**
     * Resolve the DailyDeparture for this child/date after checking the user may
     * edit it: staff or the child's parent, a current-week weekday, not yet departed.
     */
    private function authorizedDeparture(Child $child, string $date): DailyDeparture
    {
        // Adjusting a child's week is staff-or-guardian, same as editing the child.
        $this->authorize('update', $child);

        $day = Carbon::parse($date)->startOfDay();

        // Any weekday from today on (this week or a later week); never the past.
        abort_unless(
            $day->greaterThanOrEqualTo(Carbon::today()) && $day->isWeekday(),
            403,
        );

        // Schließzeit: there is no day to plan for.
        abort_if(HolidayPeriod::closesOn($day), 403);

        $departure = DailyDeparture::firstOrNew([
            'child_id' => $child->id,
            'date' => $day->toDateString(),
        ]);

        $careDay = HolidayCareDay::query()->onDate($day)->first();

        // Ferienbetreuung: a plan only exists for a child who signed up, and signing up
        // runs through /care because that is where the Anmeldeschluss is enforced.
        // Without this, editing an unregistered care day would register them through
        // the back door, deadline and all. Staff may do either, as they may there too.
        abort_if(
            ! $departure->exists && ! request()->user()->isStaff() && $careDay !== null,
            403,
            'Für diesen Ferienbetreuungstag ist das Kind nicht angemeldet.',
        );

        // Staff planning an unregistered care day *is* signing the child up, so the row
        // has to say so — otherwise it would be a plan override the board ignores.
        if ($careDay && ! $departure->exists) {
            $departure->holiday_care_day_id = $careDay->id;
        }

        abort_if(
            $departure->exists && $departure->status !== DepartureStatus::Present,
            403,
            'Dieser Tag wurde bereits abgeschlossen.',
        );

        return $departure;
    }

    /**
     * The plan fields shown in the activity log, as display strings, keyed by the
     * (translated) field key. „Art"/„Begleitkind" let it show geht-allein vs -mit.
     *
     * @return array<string, ?string>
     */
    private function planSnapshot(DailyDeparture $departure): array
    {
        return [
            'planned_time' => $this->planValue('planned_time', $departure->planned_time),
            'method' => $this->planValue('planned_method', $departure->planned_method),
            'time_qualifier' => $this->planValue('time_qualifier', $departure->time_qualifier),
            'companion' => $this->planValue('companion_child_id', $departure->companion_child_id),
        ];
    }

    /**
     * Keep only the fields that actually changed, split into new/old.
     *
     * @param  array<string, ?string>  $before
     * @param  array<string, ?string>  $after
     * @return array{attributes: array<string, ?string>, old: array<string, ?string>}
     */
    private function diff(array $before, array $after): array
    {
        $new = [];
        $old = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $new[$key] = $value;
                $old[$key] = $before[$key] ?? null;
            }
        }

        return ['attributes' => $new, 'old' => $old];
    }

    private function planValue(string $column, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($column === 'companion_child_id') {
            return Child::find($value)?->name;
        }

        return $value instanceof \BackedEnum ? $value->value : (string) $value;
    }
}
