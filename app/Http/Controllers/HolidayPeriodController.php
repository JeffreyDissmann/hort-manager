<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HolidayPeriodType;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\Setting;
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

        $closure->update($this->validated($request));

        // Extending the range offers the new weekdays; days already edited are left
        // alone, and days now outside the range are dropped.
        if ($closure->isCare()) {
            $closure->careDays()
                ->where(fn ($q) => $q->whereDate('date', '<', $closure->starts_on)
                    ->orWhereDate('date', '>', $closure->ends_on))
                ->delete();

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

        $careDay->update($validated);

        return back()->with('status', __('flash.care_day_saved'));
    }

    /** Stop offering a single day (a Betriebsausflug mid-Ferienbetreuung, say). */
    public function destroyCareDay(Request $request, HolidayCareDay $careDay): RedirectResponse
    {
        $this->authorize('update', $careDay->period);

        // Registrations for that day go with it (cascade) — nobody can attend a day
        // that isn't offered any more.
        $careDay->delete();

        return back()->with('status', __('flash.care_day_removed'));
    }

    public function destroy(Request $request, HolidayPeriod $closure): RedirectResponse
    {
        $this->authorize('delete', $closure);

        $closure->delete();

        return back()->with('status', __('flash.closure_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(HolidayPeriodType::class)],
            'starts_on' => ['required', 'date'],
            // A single closed day is starts_on == ends_on, so equality is allowed.
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            // Opting in after the Ferienbetreuung has started makes no sense.
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['type'] ??= HolidayPeriodType::Closed->value;

        // Only a Ferienbetreuung has anything to register for.
        if ($validated['type'] !== HolidayPeriodType::Care->value) {
            $validated['registration_deadline'] = null;
        }

        return $validated;
    }
}
