<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AbsenceReason;
use App\Http\Requests\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\Child;
use App\Support\LateChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Parents/staff report a child as away (krank/abwesend) — no pickup expected. */
class AbsenceController extends Controller
{
    public function store(StoreAbsenceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $child = Child::findOrFail($validated['child_id']);
        $this->authorize('update', $child);

        $reason = AbsenceReason::from($validated['reason']);
        $to = Carbon::parse($validated['to']);

        for ($date = Carbon::parse($validated['from']); $date->lte($to); $date->addDay()) {
            $absence = Absence::report($child, $date->toDateString(), $reason, $request->user()->id, $validated['comment'] ?? null);

            // Reporting today's absence after the cutoff is exactly what staff need
            // to hear about; re-saving an unchanged one isn't (wasChanged() is false).
            if ($absence->wasRecentlyCreated || $absence->wasChanged()) {
                LateChange::notify(
                    $request->user(),
                    $child,
                    $date->toDateString(),
                    $reason->label(),
                );
            }
        }

        return back()->with('status', __('flash.absence_reported', ['name' => $child->name, 'reason' => $reason->label()]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'child_id' => ['required', 'exists:children,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $child = Child::findOrFail($validated['child_id']);
        $this->authorize('update', $child);

        // Delete per model (not a bulk query) so each removal fires the „deleted"
        // event and lands in the Protokoll — otherwise clearing a Krankmeldung would
        // vanish silently while reporting one is logged.
        Absence::where('child_id', $child->id)
            ->whereBetween('date', [$validated['from'], $validated['to']])
            ->get()
            ->each->delete();

        return back()->with('status', __('flash.absence_cleared', ['name' => $child->name]));
    }
}
