<?php

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChildController extends Controller
{
    /**
     * Staff see and manage every child; parents see only their own
     * (which they may edit). Reading the shared overview lives in Wochenplan.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = $user->isStaff()
            ? Child::query()
            : $user->children()->getQuery();

        return Inertia::render('Children/Index', [
            'children' => $query
                ->orderBy('name')
                ->get(['children.id', 'name', 'date_of_birth', 'note']),
            // Delete stays staff-only; anyone may add a child.
            'canManage' => $user->isStaff(),
            'canCreate' => $user->can('create', Child::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Child::class);

        return Inertia::render('Children/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Child::class);

        $child = Child::create($this->validateChild($request));

        // A parent who creates a child becomes its guardian.
        if (! $request->user()->isStaff()) {
            $child->guardians()->attach($request->user());
        }

        return redirect()
            ->route('children.edit', $child)
            ->with('status', 'Kind angelegt. Jetzt den Stammplan festlegen.');
    }

    /** Edit a child plus their weekly Stammplan (all five weekdays). */
    public function edit(Request $request, Child $child): Response
    {
        $this->authorize('update', $child);

        $child->load('weeklySchedules');

        // Build a complete Mo–Fr grid, filling gaps with empty entries.
        $byWeekday = $child->weeklySchedules->keyBy('weekday');

        $schedule = collect(range(1, 5))->map(fn (int $weekday) => [
            'weekday' => $weekday,
            'planned_time' => $byWeekday->get($weekday)?->planned_time,
            'method' => $byWeekday->get($weekday)?->method?->value,
            'comment' => $byWeekday->get($weekday)?->comment,
        ])->all();

        $canManageGuardians = $request->user()->can('manageGuardians', $child);

        return Inertia::render('Children/Edit', [
            'child' => $child->only(['id', 'name', 'date_of_birth', 'note']),
            'schedule' => $schedule,
            'methodOptions' => collect(DepartureMethod::cases())
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->all(),
            'canManageGuardians' => $canManageGuardians,
            // Staff and the child's guardians get the parent picker + current links.
            'allParents' => $canManageGuardians
                ? User::where('role', UserRole::Parent)->orderBy('name')->get(['id', 'name', 'email'])
                : [],
            'guardianIds' => $canManageGuardians
                ? $child->guardians()->pluck('users.id')
                : [],
        ]);
    }

    public function update(Request $request, Child $child): RedirectResponse
    {
        $this->authorize('update', $child);

        $child->update($this->validateChild($request));

        $validated = $request->validate([
            'schedule' => ['array'],
            'schedule.*.weekday' => ['required', 'integer', 'between:1,5'],
            'schedule.*.planned_time' => ['nullable', 'date_format:H:i'],
            'schedule.*.method' => ['nullable', Rule::enum(DepartureMethod::class)],
            'schedule.*.comment' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['schedule'] ?? [] as $row) {
            // A weekday counts as a Hort day only when a pickup time is set.
            if (empty($row['planned_time'])) {
                $child->weeklySchedules()->where('weekday', $row['weekday'])->delete();

                continue;
            }

            $child->weeklySchedules()->updateOrCreate(
                ['weekday' => $row['weekday']],
                [
                    'planned_time' => $row['planned_time'],
                    'method' => $row['method'] ?? null,
                    'comment' => $row['comment'] ?? null,
                ],
            );
        }

        // Staff or a guardian may set which parents are linked to the child.
        if ($request->user()->can('manageGuardians', $child)) {
            $guardians = $request->validate([
                'guardians' => ['array'],
                'guardians.*' => [Rule::exists('users', 'id')->where('role', UserRole::Parent->value)],
            ]);

            $ids = $guardians['guardians'] ?? [];

            // A parent can't drop themselves as guardian (they'd lose access).
            if (! $request->user()->isStaff()) {
                $ids[] = $request->user()->id;
            }

            $child->guardians()->sync(array_unique($ids));
        }

        return redirect()
            ->route('children.index')
            ->with('status', "Stammplan für {$child->name} gespeichert.");
    }

    public function destroy(Child $child): RedirectResponse
    {
        $this->authorize('delete', $child);

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
