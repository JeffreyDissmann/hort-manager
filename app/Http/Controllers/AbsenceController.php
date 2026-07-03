<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AbsenceReason;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Parents/staff report a child as away (krank/abwesend) — no pickup expected. */
class AbsenceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'child_id' => ['required', 'exists:children,id'],
            'from' => ['required', 'date', 'after_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'reason' => ['required', Rule::enum(AbsenceReason::class)],
        ]);

        $child = Child::findOrFail($validated['child_id']);
        $this->authorize('update', $child);

        $reason = AbsenceReason::from($validated['reason']);
        $to = Carbon::parse($validated['to']);

        for ($date = Carbon::parse($validated['from']); $date->lte($to); $date->addDay()) {
            $day = $date->toDateString();

            Absence::updateOrCreate(
                ['child_id' => $child->id, 'date' => $day],
                ['reason' => $reason, 'reported_by' => $request->user()->id],
            );

            // An away child has no pending pickup — drop a not-yet-departed row.
            DailyDeparture::where('child_id', $child->id)
                ->where('date', $day)
                ->whereNull('left_at')
                ->delete();
        }

        return back()->with('status', "{$child->name} als „{$reason->label()}“ gemeldet.");
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

        return back()->with('status', "Abwesenheit für {$child->name} aufgehoben.");
    }
}
