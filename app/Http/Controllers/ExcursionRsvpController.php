<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncExcursionRsvp;
use App\Models\Child;
use App\Models\Excursion;
use App\Support\CareSignupData;
use App\Support\ExcursionPickup;
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

        // Load every invited child (open-information policy) — the parent answers for
        // their own, and can also see the whole group's status per excursion.
        $excursions = Excursion::query()
            ->whereHas('children', fn ($q) => $q->whereIn('children.id', $childIds))
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->orderBy('date')
            ->get();

        $ownChildren = Child::whereKey($childIds)->get()->keyBy('id');

        $excursions = $excursions
            ->map(function (Excursion $e) use ($childIds, $ownChildren) {
                $toRow = fn (Child $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'response' => $c->pivot->response === null ? null : (bool) $c->pivot->response,
                ];

                // Own children additionally carry their pickup for that date: joining
                // moves a clashing pickup by itself, and the page says which it is.
                $toOwnRow = fn (Child $c) => [
                    ...$toRow($c),
                    'plan' => ExcursionPickup::state($e, $ownChildren[$c->id] ?? $c),
                ];

                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'date' => $e->date->toDateString(),
                    'depart_at' => $e->depart_at ? substr((string) $e->depart_at, 0, 5) : null,
                    'return_at' => $e->return_at ? substr((string) $e->return_at, 0, 5) : null,
                    'rsvp_deadline' => $e->rsvp_deadline?->toDateString(),
                    'note' => $e->note,
                    'poll_open' => $e->pollIsOpen(),
                    // The parent's own children — the ones with answer buttons.
                    'children' => $e->children->whereIn('id', $childIds)->values()->map($toOwnRow),
                    // Everyone invited, with their status (for the "Alle Kinder" list),
                    // ordered joining → undecided → not coming.
                    'all_children' => $e->childrenByStatus()->map($toRow),
                ];
            });

        $today = now()->toDateString();

        return Inertia::render('Excursions/Poll', [
            // Split by date (like the staff view); answering is gated on poll_open.
            'upcoming' => $excursions->filter(fn ($e) => $e['date'] >= $today)->values(),
            'past' => $excursions->filter(fn ($e) => $e['date'] < $today)->sortByDesc('date')->values(),
            // „Ausflüge & Ferien": everything that wants an answer from this family on
            // one page. The Ferienbetreuung sign-up sheet is the other half of it.
            'care' => CareSignupData::for($user, ownChildrenOnly: true),
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

        activity()
            ->causedBy($user)
            ->performedOn($excursion)
            ->event($validated['response'] ? 'rsvp_yes' : 'rsvp_no')
            ->log($child->name.' · '.$excursion->name);

        // Joining the trip moves a pickup that would fall inside it — the child can't be
        // handed over while the group is away. That one day only; the Stammplan stays.
        $moved = $validated['response']
            ? ExcursionPickup::moveToReturn($excursion, $child, $user)
            : null;

        // Keep the Slack DMs in sync (buttons → result) for both guardians, queued.
        SyncExcursionRsvp::dispatch($excursion, $child);

        return back()->with('status', $moved === null
            ? __('flash.rsvp_saved', ['name' => $child->name])
            : __('flash.rsvp_saved_pickup_moved', [
                'name' => $child->name,
                'time' => substr((string) $excursion->return_at, 0, 5),
                'was' => $moved,
            ]));
    }
}
