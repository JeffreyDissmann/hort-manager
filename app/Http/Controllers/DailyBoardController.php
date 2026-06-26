<?php

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Child;
use App\Models\DailyDeparture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DailyBoardController extends Controller
{
    private const WEEKDAYS_DE = [
        1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch',
        4 => 'Donnerstag', 5 => 'Freitag',
    ];

    /** The live daily board for today (or the next weekday on weekends). */
    public function index(Request $request): Response
    {
        $date = $this->targetDate();
        $weekday = $date->dayOfWeekIso;
        $user = $request->user();

        // Seed a row per scheduled child from the Stammplan (idempotent).
        $scheduled = Child::query()
            ->whereHas('weeklySchedules', fn ($q) => $q->where('weekday', $weekday)->whereNotNull('planned_time'))
            ->with(['weeklySchedules' => fn ($q) => $q->where('weekday', $weekday)])
            ->get();

        $standard = [];
        foreach ($scheduled as $child) {
            $schedule = $child->weeklySchedules->first();
            $standard[$child->id] = [
                'time' => substr((string) $schedule->planned_time, 0, 5),
                'method' => $schedule->method?->value,
            ];

            DailyDeparture::firstOrCreate(
                ['child_id' => $child->id, 'date' => $date->toDateString()],
                [
                    'planned_time' => $schedule->planned_time,
                    'planned_method' => $schedule->method,
                    'status' => DepartureStatus::Present,
                ],
            );
        }

        $myChildIds = $user->isStaff()
            ? null
            : $user->children()->pluck('children.id');

        $departures = DailyDeparture::query()
            ->with(['child:id,name', 'markedBy:id,name'])
            ->where('date', $date->toDateString())
            ->get()
            ->sortBy(fn (DailyDeparture $d) => [$d->planned_time ?? '99:99', $d->child->name])
            ->values();

        $rows = $departures->map(function (DailyDeparture $d) use ($standard, $user, $myChildIds) {
            $plannedTime = $d->planned_time ? substr((string) $d->planned_time, 0, 5) : null;
            $plannedMethod = $d->planned_method?->value;
            $std = $standard[$d->child_id] ?? null;

            return [
                'id' => $d->id,
                'child_id' => $d->child_id,
                'name' => $d->child->name,
                'planned_time' => $plannedTime,
                'planned_method' => $plannedMethod,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'left_at' => $d->left_at?->format('H:i'),
                'marked_by' => $d->markedBy?->name,
                'note' => $d->note,
                'is_overridden' => $std === null
                    || $std['time'] !== $plannedTime
                    || $std['method'] !== $plannedMethod,
                'can_override' => $user->isStaff() || ($myChildIds?->contains($d->child_id) ?? false),
            ];
        });

        return Inertia::render('Board/Index', [
            'date' => [
                'iso' => $date->toDateString(),
                'label' => self::WEEKDAYS_DE[$weekday].', '.$date->format('d.m.Y'),
                'is_today' => $date->isToday(),
            ],
            'rows' => $rows,
            'canMark' => $user->isStaff(),
            'methodOptions' => collect(DepartureMethod::cases())
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->all(),
        ]);
    }

    /** Staff record (or undo) a child's departure. */
    public function mark(Request $request, DailyDeparture $departure): RedirectResponse
    {
        abort_unless($request->user()->isStaff(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                DepartureStatus::Present->value,
                DepartureStatus::PickedUp->value,
                DepartureStatus::SentHome->value,
            ])],
        ]);

        $status = DepartureStatus::from($validated['status']);

        $departure->update($status->hasLeft()
            ? ['status' => $status, 'left_at' => now(), 'marked_by' => $request->user()->id]
            : ['status' => $status, 'left_at' => null, 'marked_by' => null]);

        return back();
    }

    /** Same-day change to the plan — by staff or the child's own parent. */
    public function override(Request $request, DailyDeparture $departure): RedirectResponse
    {
        $this->authorize('update', $departure->child);

        $validated = $request->validate([
            'planned_time' => ['required', 'date_format:H:i'],
            'planned_method' => ['nullable', Rule::enum(DepartureMethod::class)],
        ]);

        $departure->update([
            'planned_time' => $validated['planned_time'],
            'planned_method' => $validated['planned_method'] ?? null,
        ]);

        return back()->with('status', "Plan für {$departure->child->name} aktualisiert.");
    }

    /** Today, advancing across the weekend to the next weekday. */
    private function targetDate(): Carbon
    {
        $date = Carbon::today();

        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }
}
