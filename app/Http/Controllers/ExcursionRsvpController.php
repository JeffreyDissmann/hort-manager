<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncExcursionRsvp;
use App\Models\Child;
use App\Models\Excursion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExcursionRsvpController extends Controller
{
    /** The parent's poll page: open excursions with their children to answer for. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $childIds = $user->children()->pluck('children.id');

        $excursions = Excursion::query()
            ->whereHas('children', fn ($q) => $q->whereIn('children.id', $childIds))
            ->with(['children' => fn ($q) => $q->whereIn('children.id', $childIds)->orderBy('name')])
            ->orderBy('date')
            ->get()
            ->map(fn (Excursion $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'date' => $e->date->toDateString(),
                'depart_at' => $e->depart_at ? substr((string) $e->depart_at, 0, 5) : null,
                'return_at' => $e->return_at ? substr((string) $e->return_at, 0, 5) : null,
                'rsvp_deadline' => $e->rsvp_deadline?->toDateString(),
                'note' => $e->note,
                'poll_open' => $e->pollIsOpen(),
                'children' => $e->children->map(fn (Child $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'response' => $c->pivot->response === null ? null : (bool) $c->pivot->response,
                ]),
            ]);

        $today = now()->toDateString();

        return Inertia::render('Excursions/Poll', [
            // Split by date (like the staff view); answering is gated on poll_open.
            'upcoming' => $excursions->filter(fn ($e) => $e['date'] >= $today)->values(),
            'past' => $excursions->filter(fn ($e) => $e['date'] < $today)->sortByDesc('date')->values(),
        ]);
    }

    /** Answer the poll for one child (parent of that child, or staff). */
    public function update(Request $request, Excursion $excursion): RedirectResponse
    {
        $validated = $request->validate([
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'response' => ['required', 'boolean'],
        ]);

        $child = Child::findOrFail($validated['child_id']);
        $user = $request->user();

        // Answering is staff-or-guardian, same as editing the child.
        $this->authorize('update', $child);

        // Parents can only answer while the poll is open; staff may fix it up anytime.
        if (! $user->isStaff()) {
            abort_unless($excursion->pollIsOpen(), 403);
        }

        $excursion->children()->syncWithoutDetaching([
            $child->id => [
                'response' => $validated['response'],
                'answered_by' => $user->id,
                'answered_at' => now(),
            ],
        ]);

        // Keep the Slack DMs in sync (buttons → result) for both guardians, queued.
        SyncExcursionRsvp::dispatch($excursion, $child);

        return back()->with('status', "Antwort für {$child->name} gespeichert.");
    }
}
