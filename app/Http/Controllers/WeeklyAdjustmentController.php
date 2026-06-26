<?php

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Child;
use App\Models\DailyDeparture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class WeeklyAdjustmentController extends Controller
{
    /** Set an individual adjustment for one child on one day of the current week. */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'date' => ['required', 'date'],
            'planned_time' => ['nullable', 'date_format:H:i'],
            'planned_method' => ['nullable', Rule::enum(DepartureMethod::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $child = Child::findOrFail($validated['child_id']);
        $departure = $this->authorizedDeparture($request, $child, $validated['date']);

        $departure->fill([
            'planned_time' => $validated['planned_time'] ?? null,
            'planned_method' => $validated['planned_method'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        if (! $departure->exists) {
            $departure->status = DepartureStatus::Present;
        }

        $departure->save();

        return back()->with('status', "Plan für {$child->name} aktualisiert.");
    }

    /** Revert one day back to the standard Stammplan. */
    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'date' => ['required', 'date'],
        ]);

        $child = Child::findOrFail($validated['child_id']);
        $departure = $this->authorizedDeparture($request, $child, $validated['date']);

        // Deleting the override row makes the board fall back to the Stammplan.
        if ($departure->exists) {
            $departure->delete();
        }

        return back()->with('status', "{$child->name}: Tag auf Standard zurückgesetzt.");
    }

    /**
     * Resolve the DailyDeparture for this child/date after checking the user may
     * edit it: staff or the child's parent, a current-week weekday, not yet departed.
     */
    private function authorizedDeparture(Request $request, Child $child, string $date): DailyDeparture
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $child->isGuardedBy($user), 403);

        $day = Carbon::parse($date)->startOfDay();

        // Any weekday from today on (this week or a later week); never the past.
        abort_unless(
            $day->greaterThanOrEqualTo(Carbon::today()) && $day->isWeekday(),
            403,
        );

        $departure = DailyDeparture::firstOrNew([
            'child_id' => $child->id,
            'date' => $day->toDateString(),
        ]);

        abort_if(
            $departure->exists && $departure->status !== DepartureStatus::Present,
            403,
            'Dieser Tag wurde bereits abgeschlossen.',
        );

        return $departure;
    }
}
