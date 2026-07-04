<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
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

        // Children reported away today (krank/abwesend) — no pickup expected.
        $absences = Absence::with('child:id,name')
            ->where('date', $date->toDateString())
            ->get();
        $absentChildIds = $absences->pluck('child_id')->all();

        // Seed a row per scheduled child from the Stammplan (idempotent).
        $scheduled = Child::query()
            ->whereHas('weeklySchedules', fn ($q) => $q->where('weekday', $weekday)->whereNotNull('planned_time'))
            ->with(['weeklySchedules' => fn ($q) => $q->where('weekday', $weekday)])
            ->get();

        $standard = [];
        foreach ($scheduled as $child) {
            if (in_array($child->id, $absentChildIds, true)) {
                continue; // away today → not on the pickup board
            }

            $schedule = $child->weeklySchedules->first();
            $standard[$child->id] = [
                'time' => substr((string) $schedule->planned_time, 0, 5),
                'method' => $schedule->method?->value,
                'comment' => $schedule->comment,
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

        // Excursions today: a group list (with live state) plus a per-child overlay.
        $excursionByChild = [];
        $excursionList = [];
        $excursions = Excursion::query()
            ->with('participants:id')
            ->whereDate('date', $date->toDateString())
            ->orderBy('depart_at')
            ->get();
        foreach ($excursions as $excursion) {
            $state = $excursion->state();
            $departAt = $excursion->depart_at ? substr((string) $excursion->depart_at, 0, 5) : null;
            $returnAt = $excursion->return_at ? substr((string) $excursion->return_at, 0, 5) : null;

            $excursionList[] = [
                'id' => $excursion->id,
                'name' => $excursion->name,
                'depart_at' => $departAt,
                'return_at' => $returnAt,
                'departed_at' => $excursion->departed_at?->format('H:i'),
                'returned_at' => $excursion->returned_at?->format('H:i'),
                'child_count' => $excursion->participants->count(),
                'state' => $state,
            ];

            foreach ($excursion->participants as $child) {
                $excursionByChild[$child->id] = [
                    'name' => $excursion->name,
                    'depart_at' => $departAt,
                    'return_at' => $returnAt,
                    'departed_at' => $excursion->departed_at?->format('H:i'),
                    'returned_at' => $excursion->returned_at?->format('H:i'),
                    'state' => $state,
                ];
            }
        }

        $myChildIds = $user->isStaff()
            ? null
            : $user->children()->pluck('children.id');

        $departures = DailyDeparture::query()
            ->with(['child:id,name,date_of_birth', 'markedBy:id,name'])
            ->where('date', $date->toDateString())
            ->whereNotIn('child_id', $absentChildIds)
            ->get()
            ->sortBy(fn (DailyDeparture $d) => [$d->planned_time ?? '99:99', $d->child->name])
            ->values();

        $rows = $departures->map(function (DailyDeparture $d) use ($standard, $user, $myChildIds, $excursionByChild, $date) {
            $dob = $d->child->date_of_birth;
            $birthday = $dob && $dob->format('m-d') === $date->format('m-d')
                ? $date->year - $dob->year
                : null;
            $plannedTime = $d->planned_time ? substr((string) $d->planned_time, 0, 5) : null;
            $plannedMethod = $d->planned_method?->value;
            $std = $standard[$d->child_id] ?? null;
            $overridden = $std === null
                || $std['time'] !== $plannedTime
                || $std['method'] !== $plannedMethod;

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
                // Shown on the plan line: the override's own note, or the Stammplan comment.
                'comment' => $overridden ? $d->note : ($std['comment'] ?? null),
                // Pre-fills the override editor; defaults to the standard comment.
                'note' => $d->note ?? ($std['comment'] ?? null),
                'is_overridden' => $overridden,
                'can_override' => $user->isStaff() || ($myChildIds?->contains($d->child_id) ?? false),
                'is_own' => $myChildIds?->contains($d->child_id) ?? false,
                'excursion' => $excursionByChild[$d->child_id] ?? null,
                // Age the child turns today, or null if it's not their birthday.
                'birthday' => $birthday,
            ];
        });

        $program = DailyProgram::where('date', $date->toDateString())->first();
        $homeworkDefault = HomeworkDefault::where('weekday', $weekday)->first();
        [$homeworkStart, $homeworkEnd] = DailyProgram::effectiveHomework($program, $homeworkDefault);

        $hasProgram = $program?->lunch || $program?->activity || $homeworkStart;

        return Inertia::render('Board/Index', [
            'date' => [
                'iso' => $date->toDateString(),
                'label' => self::WEEKDAYS_DE[$weekday].', '.$date->format('d.m.Y'),
                'is_today' => $date->isToday(),
            ],
            'rows' => $rows,
            'absent' => $absences->map(fn (Absence $a) => [
                'name' => $a->child->name,
                'reason' => $a->reason->value,
                'reason_label' => $a->reason->label(),
            ])->values(),
            'excursions' => $excursionList,
            'program' => $hasProgram ? [
                'lunch' => $program?->lunch,
                'activity' => $program?->activity,
                'homework_start' => $homeworkStart ? substr((string) $homeworkStart, 0, 5) : null,
                'homework_end' => $homeworkEnd ? substr((string) $homeworkEnd, 0, 5) : null,
            ] : null,
            'canMark' => $user->isStaff(),
            'methodOptions' => collect(DepartureMethod::cases())
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->all(),
        ]);
    }

    /** Staff record (or undo) a child's departure. */
    public function mark(Request $request, DailyDeparture $departure): RedirectResponse
    {
        $this->authorize('mark', $departure);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                DepartureStatus::Present->value,
                DepartureStatus::PickedUp->value,
                DepartureStatus::SentHome->value,
            ])],
        ]);

        $status = DepartureStatus::from($validated['status']);

        // Marking off (left_at) triggers the guardian Slack DM via the observer.
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
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $departure->update([
            'planned_time' => $validated['planned_time'],
            'planned_method' => $validated['planned_method'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('status', __('flash.plan_updated', ['name' => $departure->child->name]));
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
