<?php

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Models\Child;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChildController extends Controller
{
    /** List all children. Reads are open to any authenticated user. */
    public function index(): Response
    {
        return Inertia::render('Children/Index', [
            'children' => Child::query()
                ->orderBy('name')
                ->get(['id', 'name', 'date_of_birth', 'note']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Children/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $child = Child::create($this->validateChild($request));

        return redirect()
            ->route('children.edit', $child)
            ->with('status', 'Kind angelegt. Jetzt den Stammplan festlegen.');
    }

    /** Edit a child plus their weekly Stammplan (all five weekdays). */
    public function edit(Child $child): Response
    {
        $child->load('weeklySchedules');

        // Build a complete Mo–Fr grid, filling gaps with empty entries.
        $byWeekday = $child->weeklySchedules->keyBy('weekday');

        $schedule = collect(range(1, 5))->map(fn (int $weekday) => [
            'weekday' => $weekday,
            'planned_time' => $byWeekday->get($weekday)?->planned_time,
            'method' => $byWeekday->get($weekday)?->method?->value,
        ])->all();

        return Inertia::render('Children/Edit', [
            'child' => $child->only(['id', 'name', 'date_of_birth', 'note']),
            'schedule' => $schedule,
            'methodOptions' => collect(DepartureMethod::cases())
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->all(),
        ]);
    }

    public function update(Request $request, Child $child): RedirectResponse
    {
        $child->update($this->validateChild($request));

        $validated = $request->validate([
            'schedule' => ['array'],
            'schedule.*.weekday' => ['required', 'integer', 'between:1,5'],
            'schedule.*.planned_time' => ['nullable', 'date_format:H:i'],
            'schedule.*.method' => ['nullable', Rule::enum(DepartureMethod::class)],
        ]);

        foreach ($validated['schedule'] ?? [] as $row) {
            // A weekday counts as a Hort day only when a pickup time is set.
            if (empty($row['planned_time'])) {
                $child->weeklySchedules()->where('weekday', $row['weekday'])->delete();

                continue;
            }

            $child->weeklySchedules()->updateOrCreate(
                ['weekday' => $row['weekday']],
                ['planned_time' => $row['planned_time'], 'method' => $row['method'] ?? null],
            );
        }

        return redirect()
            ->route('children.index')
            ->with('status', "Stammplan für {$child->name} gespeichert.");
    }

    public function destroy(Child $child): RedirectResponse
    {
        $name = $child->name;
        $child->delete();

        return redirect()
            ->route('children.index')
            ->with('status', "{$name} wurde gelöscht.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateChild(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
