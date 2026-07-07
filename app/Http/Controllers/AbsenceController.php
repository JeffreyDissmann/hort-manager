<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AbsenceReason;
use App\Http\Requests\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\Child;
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
            Absence::report($child, $date->toDateString(), $reason, $request->user()->id, $validated['comment'] ?? null);
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

        Absence::where('child_id', $child->id)
            ->whereBetween('date', [$validated['from'], $validated['to']])
            ->delete();

        return back()->with('status', __('flash.absence_cleared', ['name' => $child->name]));
    }
}
