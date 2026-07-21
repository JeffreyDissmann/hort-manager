<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Models\Accounting\Booking;
use App\Models\Child;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                ->with('guardians:id,name')
                ->orderBy('name')
                ->get(['children.id', 'name', 'date_of_birth', 'note'])
                ->map(fn (Child $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
                    'note' => $child->note,
                    'can_delete' => $user->can('delete', $child),
                    // Guardians linked to this child (open-information policy).
                    'guardians' => $child->guardians->sortBy('name')->pluck('name')->values(),
                ]),
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
            ->with('status', __('flash.child_created'));
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
            'time_qualifier' => $byWeekday->get($weekday)?->time_qualifier?->value,
            'comment' => $byWeekday->get($weekday)?->comment,
        ])->all();

        $canManageGuardians = $request->user()->can('manageGuardians', $child);

        return Inertia::render('Children/Edit', [
            'child' => [
                'id' => $child->id,
                'name' => $child->name,
                // Plain Y-m-d so the <input type="date"> can display it.
                'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
                'note' => $child->note,
            ],
            'schedule' => $schedule,
            // „Geht mit einem anderen Kind mit" is a per-day Wochenplan choice, never
            // part of the fixed Stammplan — so only picked-up / goes-home-alone here.
            'methodOptions' => collect(DepartureMethod::cases())
                ->reject(fn (DepartureMethod $m) => $m === DepartureMethod::WithChild)
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->values()
                ->all(),
            // „Geht allein" may say the time means bis / genau um / ab.
            'qualifierOptions' => collect(TimeQualifier::cases())
                ->map(fn (TimeQualifier $q) => ['value' => $q->value, 'label' => $q->label()])
                ->all(),
            'canDelete' => $request->user()->can('delete', $child),
            'canManageGuardians' => $canManageGuardians,
            // Staff and the child's guardians get the parent picker + current links.
            'allParents' => $canManageGuardians
                ? User::where('role', UserRole::Parent)->orderBy('name')->get(['id', 'name', 'email', 'avatar'])
                : [],
            'guardianIds' => $canManageGuardians
                ? $child->guardians()->pluck('users.id')
                : [],
        ]);
    }

    public function update(Request $request, Child $child): RedirectResponse
    {
        $this->authorize('update', $child);

        // Validate everything up front so a later failure can't leave a partially
        // applied update (e.g. name saved but the schedule rejected).
        $childData = $this->validateChild($request);

        $schedule = $request->validate([
            'schedule' => ['array'],
            'schedule.*.weekday' => ['required', 'integer', 'between:1,5'],
            'schedule.*.planned_time' => ['nullable', 'date_format:H:i'],
            // Companion („with_child") is Wochenplan-only; the Stammplan can't set it.
            'schedule.*.method' => ['nullable', Rule::enum(DepartureMethod::class)->except([DepartureMethod::WithChild])],
            'schedule.*.time_qualifier' => ['nullable', Rule::enum(TimeQualifier::class)],
            'schedule.*.comment' => ['nullable', 'string', 'max:255'],
        ])['schedule'] ?? [];

        // Staff or a guardian may set which parents are linked to the child.
        $guardianIds = null;
        if ($request->user()->can('manageGuardians', $child)) {
            $guardianIds = $request->validate([
                'guardians' => ['array'],
                'guardians.*' => [Rule::exists('users', 'id')->where('role', UserRole::Parent->value)],
            ])['guardians'] ?? [];

            // A parent can't drop themselves as guardian (they'd lose access).
            if (! $request->user()->isStaff()) {
                $guardianIds[] = $request->user()->id;
            }
        }

        // All validated — apply atomically.
        DB::transaction(function () use ($child, $childData, $schedule, $guardianIds) {
            $child->update($childData);

            foreach ($schedule as $row) {
                // A weekday counts as a Hort day only when a pickup time is set.
                if (empty($row['planned_time'])) {
                    $child->weeklySchedules()->where('weekday', $row['weekday'])->delete();

                    continue;
                }

                // The qualifier only qualifies a „geht allein" time.
                $isSentHome = ($row['method'] ?? null) === DepartureMethod::SentHome->value;

                $child->weeklySchedules()->updateOrCreate(
                    ['weekday' => $row['weekday']],
                    [
                        'planned_time' => $row['planned_time'],
                        'method' => $row['method'] ?? null,
                        'time_qualifier' => $isSentHome ? ($row['time_qualifier'] ?? null) : null,
                        'comment' => $row['comment'] ?? null,
                    ],
                );
            }

            if ($guardianIds !== null) {
                $child->guardians()->sync(array_unique($guardianIds));
            }
        });

        if ($guardianIds !== null) {
            activity()
                ->causedBy($request->user())
                ->performedOn($child)
                ->event('guardians')
                ->log($child->name);
        }

        return redirect()
            ->route('children.index')
            ->with('status', __('flash.schedule_saved', ['name' => $child->name]));
    }

    public function destroy(Child $child): RedirectResponse
    {
        $this->authorize('delete', $child);

        // Keep the ledger intact: a child with any booking attributed to it can't be
        // deleted (the payments would lose their reference).
        if (Booking::where('counterparty_child_id', $child->id)->exists()) {
            return back()->with('error', __('flash.child_has_bookings', ['name' => $child->name]));
        }

        $name = $child->name;
        $child->delete();

        return redirect()
            ->route('children.index')
            ->with('status', __('flash.child_deleted', ['name' => $name]));
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
