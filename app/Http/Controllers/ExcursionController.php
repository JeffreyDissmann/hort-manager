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
            ->withCount('children')
            ->with('children:id,name')
            ->orderByDesc('date')
            ->get()
            ->map(fn (Excursion $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'date' => $e->date->toDateString(),
                'depart_at' => $this->time($e->depart_at),
                'return_at' => $this->time($e->return_at),
                'child_count' => $e->children_count,
                'children' => $e->children->pluck('name'),
            ]);

        return Inertia::render('Excursions/Index', [
            'excursions' => $excursions,
        ]);
    }

    public function create(): Response
    {
        $this->ensureStaff();

        return Inertia::render('Excursions/Create', [
            'allChildren' => Child::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureStaff();

        $data = $this->validateExcursion($request);

        $excursion = Excursion::create($data['attributes']);
        $excursion->children()->sync($data['children']);

        return redirect()
            ->route('excursions.index')
            ->with('status', "Ausflug „{$excursion->name}\" angelegt.");
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
                'note' => $excursion->note,
            ],
            'childIds' => $excursion->children()->pluck('children.id'),
            'allChildren' => Child::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Excursion $excursion): RedirectResponse
    {
        $this->ensureStaff();

        $data = $this->validateExcursion($request);

        $excursion->update($data['attributes']);
        $excursion->children()->sync($data['children']);

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
     * @return array{attributes: array<string, mixed>, children: array<int>}
     */
    private function validateExcursion(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'depart_at' => ['nullable', 'date_format:H:i'],
            'return_at' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
            'children' => ['array'],
            'children.*' => ['integer', 'exists:children,id'],
        ]);

        return [
            'attributes' => collect($validated)->only(['name', 'date', 'depart_at', 'return_at', 'note'])->all(),
            'children' => $validated['children'] ?? [],
        ];
    }
}
