<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Excursion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ExcursionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Excursion::class);

        $excursions = Excursion::query()
            ->with('children:id,name')
            ->orderBy('date')
            ->get()
            ->map(function (Excursion $e) {
                // Pivot constraints inside withCount subqueries are unreliable, so
                // derive the counts from the loaded answers directly.
                $children = $e->children;
                $joining = $children->filter(fn ($c) => $c->pivot->response !== null && (bool) $c->pivot->response);
                $declined = $children->filter(fn ($c) => $c->pivot->response !== null && ! (bool) $c->pivot->response);
                $pending = $children->filter(fn ($c) => $c->pivot->response === null);

                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'date' => $e->date->toDateString(),
                    'depart_at' => $this->time($e->depart_at),
                    'return_at' => $this->time($e->return_at),
                    'rsvp_deadline' => $e->rsvp_deadline?->toDateString(),
                    'poll_open' => $e->pollIsOpen(),
                    'joining_count' => $joining->count(),
                    'declined_count' => $declined->count(),
                    'pending_count' => $pending->count(),
                    // Everyone invited, with their status, ordered joining → undecided
                    // → not coming (the teacher view shows this list always expanded).
                    'all_children' => $e->childrenByStatus()->map(fn (Child $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'response' => $c->pivot->response === null ? null : (bool) $c->pivot->response,
                    ]),
                ];
            });

        $today = now()->toDateString();

        return Inertia::render('Excursions/Index', [
            // Soonest first for upcoming, most-recent first for the history.
            'upcoming' => $excursions->filter(fn ($e) => $e['date'] >= $today)->values(),
            'past' => $excursions->filter(fn ($e) => $e['date'] < $today)->sortByDesc('date')->values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Excursion::class);

        // Suggest the next Friday, skipping any Friday that already has a trip.
        $friday = Carbon::today()->next(Carbon::FRIDAY);
        $taken = Excursion::get(['date'])->map(fn (Excursion $e) => $e->date->toDateString())->all();
        while (in_array($friday->toDateString(), $taken, true)) {
            $friday->addWeek();
        }

        return Inertia::render('Excursions/Create', [
            'suggestedDate' => $friday->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Excursion::class);

        $excursion = Excursion::create($this->validateExcursion($request));

        // Invite every child enrolled on the trip date — each starts as an open poll
        // entry. (Creating the excursion fires the ExcursionObserver, which DMs guardians.)
        $excursion->children()->attach(Child::activeOn($excursion->date)->pluck('id')->all());

        return redirect()
            ->route('excursions.index')
            ->with('status', __('flash.excursion_created', ['name' => $excursion->name]));
    }

    public function edit(Excursion $excursion): Response
    {
        $this->authorize('update', $excursion);

        return Inertia::render('Excursions/Edit', [
            'excursion' => [
                'id' => $excursion->id,
                'name' => $excursion->name,
                'date' => $excursion->date->toDateString(),
                'depart_at' => $this->time($excursion->depart_at),
                'return_at' => $this->time($excursion->return_at),
                'rsvp_deadline' => $excursion->rsvp_deadline?->toDateString(),
                'note' => $excursion->note,
            ],
            'children' => $excursion->children()
                ->orderBy('name')
                ->get()
                ->map(fn (Child $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'response' => $c->pivot->response === null ? null : (bool) $c->pivot->response,
                ]),
        ]);
    }

    public function update(Request $request, Excursion $excursion): RedirectResponse
    {
        $this->authorize('update', $excursion);

        $excursion->update($this->validateExcursion($request));

        // Keep the poll complete if children (enrolled on the trip date) were added
        // after the excursion was created.
        $missing = Child::activeOn($excursion->date)
            ->whereNotIn('id', $excursion->children()->pluck('children.id'))->pluck('id');
        $excursion->children()->attach($missing->all());

        return redirect()
            ->route('excursions.index')
            ->with('status', __('flash.excursion_saved', ['name' => $excursion->name]));
    }

    public function destroy(Excursion $excursion): RedirectResponse
    {
        $this->authorize('delete', $excursion);

        $name = $excursion->name;
        $excursion->delete();

        return redirect()
            ->route('excursions.index')
            ->with('status', __('flash.excursion_deleted', ['name' => $name]));
    }

    /** Staff flip the live trip state from the Tagesboard on the day itself. */
    public function live(Request $request, Excursion $excursion): RedirectResponse
    {
        $this->authorize('update', $excursion);

        $validated = $request->validate([
            'event' => ['required', 'in:depart,return,undo_depart,undo_return'],
        ]);

        match ($validated['event']) {
            'depart' => $excursion->update(['departed_at' => now()]),
            'undo_depart' => $excursion->update(['departed_at' => null, 'returned_at' => null]),
            'return' => $excursion->update(['returned_at' => now()]),
            'undo_return' => $excursion->update(['returned_at' => null]),
        };

        return back();
    }

    private function time(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateExcursion(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'depart_at' => ['nullable', 'date_format:H:i'],
            'return_at' => ['nullable', 'date_format:H:i'],
            'rsvp_deadline' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
