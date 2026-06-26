<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Excursion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExcursionController extends Controller
{
    public function index(): Response
    {
        $this->ensureStaff();

        $excursions = Excursion::query()
            ->withCount([
                'children as joining_count' => fn ($q) => $q->wherePivot('response', true),
                'children as declined_count' => fn ($q) => $q->wherePivot('response', false),
                'children as pending_count' => fn ($q) => $q->wherePivotNull('response'),
            ])
            ->with('participants:id,name')
            ->orderBy('date')
            ->get()
            ->map(fn (Excursion $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'date' => $e->date->toDateString(),
                'depart_at' => $this->time($e->depart_at),
                'return_at' => $this->time($e->return_at),
                'rsvp_deadline' => $e->rsvp_deadline?->toDateString(),
                'poll_open' => $e->pollIsOpen(),
                'joining_count' => $e->joining_count,
                'declined_count' => $e->declined_count,
                'pending_count' => $e->pending_count,
                'participants' => $e->participants->pluck('name'),
            ]);

        $today = now()->toDateString();

        return Inertia::render('Excursions/Index', [
            // Soonest first for upcoming, most-recent first for the history.
            'upcoming' => $excursions->filter(fn ($e) => $e['date'] >= $today)->values(),
            'past' => $excursions->filter(fn ($e) => $e['date'] < $today)->sortByDesc('date')->values(),
        ]);
    }

    public function create(): Response
    {
        $this->ensureStaff();

        return Inertia::render('Excursions/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureStaff();

        $excursion = Excursion::create($this->validateExcursion($request));

        // Invite every child — each starts as an open poll entry for the parents.
        $excursion->children()->attach(Child::pluck('id')->all());

        return redirect()
            ->route('excursions.index')
            ->with('status', "Ausflug „{$excursion->name}\" angelegt. Die Eltern wurden zur Abstimmung eingeladen.");
    }

    public function edit(Excursion $excursion): Response
    {
        $this->ensureStaff();

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
        $this->ensureStaff();

        $excursion->update($this->validateExcursion($request));

        // Keep the poll complete if children were added after the excursion was created.
        $missing = Child::whereNotIn('id', $excursion->children()->pluck('children.id'))->pluck('id');
        $excursion->children()->attach($missing->all());

        return redirect()
            ->route('excursions.index')
            ->with('status', "Ausflug „{$excursion->name}\" gespeichert.");
    }

    public function destroy(Excursion $excursion): RedirectResponse
    {
        $this->ensureStaff();

        $name = $excursion->name;
        $excursion->delete();

        return redirect()
            ->route('excursions.index')
            ->with('status', "Ausflug „{$name}\" gelöscht.");
    }

    /** Staff flip the live trip state from the Tagesboard on the day itself. */
    public function live(Request $request, Excursion $excursion): RedirectResponse
    {
        $this->ensureStaff();

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

    /** Excursion management is staff-only. */
    private function ensureStaff(): void
    {
        abort_unless(auth()->user()?->isStaff(), 403);
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
