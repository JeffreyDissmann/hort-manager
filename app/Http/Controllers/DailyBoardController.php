<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\TimeQualifier;
use App\Http\Controllers\Concerns\ResolvesDay;
use App\Http\Requests\MarkDepartureRequest;
use App\Http\Requests\OverrideDepartureRequest;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
use App\Support\CompanionNotes;
use App\Support\CompanionReconciler;
use App\Support\EffectivePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyBoardController extends Controller
{
    use ResolvesDay;

    /** The daily board for a selected day (defaults to today / next weekday). */
    public function index(Request $request): Response
    {
        [$date, $day] = $this->resolveDay($request);
        $isToday = $date->isToday();
        $weekday = $date->dayOfWeekIso;
        $user = $request->user();

        // Children reported away today (krank/abwesend) — no pickup expected.
        $absences = Absence::with('child:id,name')
            ->where('date', $date->toDateString())
            ->get();
        $absentChildIds = $absences->pluck('child_id')->all();

        // Seed a row per scheduled child from the Stammplan (idempotent). Only
        // children enrolled on this date are on the board.
        $scheduled = Child::query()
            ->activeOn($date)
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
                'qualifier' => $schedule->time_qualifier?->value,
                'comment' => $schedule->comment,
            ];

            // Only today persists a row (so marking has a real id). Other days are
            // rendered read-only/preview from the Stammplan (see synthesized rows below).
            if ($isToday) {
                DailyDeparture::firstOrCreate(
                    ['child_id' => $child->id, 'date' => $date->toDateString()],
                    [
                        'planned_time' => $schedule->planned_time,
                        'planned_method' => $schedule->method,
                        'time_qualifier' => $schedule->time_qualifier,
                        'status' => DepartureStatus::Present,
                    ],
                );
            }
        }

        // Excursions today: a group list (with live state) plus a per-child overlay.
        $excursionByChild = [];
        $excursionList = [];
        $excursions = Excursion::query()
            ->with('participants:id,name')
            ->where('date', $date->toDateString())
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
                'children' => $excursion->participants->pluck('name')->sort()->values(),
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

        $childNames = Child::query()->pluck('name', 'id'); // companion name lookup

        $departures = DailyDeparture::query()
            ->with(['child:id,name,date_of_birth', 'markedBy:id,name'])
            ->where('date', $date->toDateString())
            ->whereNotIn('child_id', $absentChildIds)
            ->get();

        // Each row's effective time — mirrored from the companion for „geht mit … mit".
        // Resolve all companions' plans in one batch to avoid a per-row query.
        $companionPlans = EffectivePlan::forMany(
            $departures->where('planned_method', DepartureMethod::WithChild)
                ->pluck('companion_child_id')->filter()->all(),
            [$date->toDateString()],
        );
        $effectiveTime = [];
        foreach ($departures as $d) {
            $effectiveTime[$d->id] = $d->planned_method === DepartureMethod::WithChild && $d->companion_child_id
                ? ($companionPlans[$d->companion_child_id.'|'.$date->toDateString()]['time'] ?? null)
                : ($d->planned_time ? substr((string) $d->planned_time, 0, 5) : null);
        }

        $editable = $day['editable'];

        $rows = $departures->map(function (DailyDeparture $d) use ($standard, $user, $myChildIds, $excursionByChild, $date, $childNames, $effectiveTime, $editable) {
            $dob = $d->child->date_of_birth;
            $birthday = $dob && $dob->format('m-d') === $date->format('m-d')
                ? $date->year - $dob->year
                : null;
            // For „geht mit … mit" the time is mirrored from the companion (see $effectiveTime).
            $plannedTime = $effectiveTime[$d->id] ?? null;
            $plannedMethod = $d->planned_method?->value;

            // The „geht mit … mit" arrangement is only shown once the companion's family
            // has confirmed; until then the board sees a normal pickup at the synced time.
            $companion = null;
            if ($plannedMethod === DepartureMethod::WithChild->value && $d->companion_child_id) {
                if ($d->companion_confirmed === true) {
                    $companion = ['id' => $d->companion_child_id, 'name' => $childNames[$d->companion_child_id] ?? '', 'confirmed' => true];
                } else {
                    $plannedMethod = DepartureMethod::PickedUp->value;
                }
            }

            // „geht allein" prefix (bis/ab); the default „genau um" stays implicit.
            $qualifier = $plannedMethod === DepartureMethod::SentHome->value ? $d->time_qualifier : null;

            $std = $standard[$d->child_id] ?? null;
            $overridden = $std === null
                || $std['time'] !== $plannedTime
                || $std['method'] !== $plannedMethod
                || ($std['qualifier'] ?? null) !== ($qualifier?->value);

            return [
                'id' => $d->id,
                'child_id' => $d->child_id,
                'name' => $d->child->name,
                'planned_time' => $plannedTime,
                'planned_method' => $plannedMethod,
                'qualifier_prefix' => $qualifier && $qualifier !== TimeQualifier::At
                    ? $qualifier->prefix()
                    : null,
                // Raw value to pre-fill the override editor (defaults to „genau um").
                'qualifier' => $d->time_qualifier?->value ?? TimeQualifier::At->value,
                'companion' => $companion,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'left_at' => $d->left_at?->format('H:i'),
                'marked_by' => $d->markedBy?->name,
                // Shown on the plan line: the override's own note, or the Stammplan comment.
                'comment' => $overridden ? $d->note : ($std['comment'] ?? null),
                // Pre-fills the override editor; defaults to the standard comment.
                'note' => $d->note ?? ($std['comment'] ?? null),
                'is_overridden' => $overridden,
                'can_override' => $editable && ($user->isStaff() || ($myChildIds?->contains($d->child_id) ?? false)),
                'is_own' => $myChildIds?->contains($d->child_id) ?? false,
                'excursion' => $excursionByChild[$d->child_id] ?? null,
                // Age the child turns today, or null if it's not their birthday.
                'birthday' => $birthday,
            ];
        });

        // Non-today days don't persist rows: synthesize a read-only/preview row from the
        // Stammplan for each scheduled child that has no DailyDeparture for the date.
        if (! $isToday) {
            $withRow = $departures->pluck('child_id')->all();
            foreach ($scheduled as $child) {
                if (in_array($child->id, $absentChildIds, true) || in_array($child->id, $withRow, true)) {
                    continue;
                }
                $std = $standard[$child->id] ?? null;
                if ($std === null) {
                    continue;
                }

                $own = $myChildIds?->contains($child->id) ?? false;
                $dob = $child->date_of_birth;

                $rows->push([
                    'id' => null,
                    'child_id' => $child->id,
                    'name' => $child->name,
                    'planned_time' => $std['time'],
                    'planned_method' => $std['method'],
                    'qualifier_prefix' => $std['method'] === DepartureMethod::SentHome->value
                        && $std['qualifier'] && $std['qualifier'] !== TimeQualifier::At->value
                        ? TimeQualifier::from($std['qualifier'])->prefix()
                        : null,
                    'qualifier' => $std['qualifier'] ?? TimeQualifier::At->value,
                    'companion' => null, // the Stammplan carries no companion
                    'status' => DepartureStatus::Present->value,
                    'status_label' => DepartureStatus::Present->label(),
                    'left_at' => null,
                    'marked_by' => null,
                    'comment' => $std['comment'],
                    'note' => $std['comment'],
                    'is_overridden' => false,
                    'can_override' => $editable && ($user->isStaff() || $own),
                    'is_own' => $own,
                    'excursion' => $excursionByChild[$child->id] ?? null,
                    'birthday' => $dob && $dob->format('m-d') === $date->format('m-d')
                        ? $date->year - $dob->year
                        : null,
                ]);
            }
        }

        $rows = $rows
            ->sortBy(fn (array $r) => [$r['planned_time'] ?? '99:99', $r['name']])
            ->values();

        $program = DailyProgram::where('date', $date->toDateString())->first();
        $homeworkDefault = HomeworkDefault::where('weekday', $weekday)->first();
        [$homeworkStart, $homeworkEnd] = DailyProgram::effectiveHomework($program, $homeworkDefault);

        $hasProgram = $program?->lunch || $program?->activity || $homeworkStart;

        // Children who regularly aren't at the Hort today (a Stammplan „Hortfrei"
        // weekday): they have a plan on other weekdays but none for today — surfaced so
        // staff know the shorter list is intentional. Excluded: unplanned children (no
        // Stammplan at all), today's reported absences, and anyone with a same-day
        // override (a manual pickup for today means they ARE here — they're on the board).
        $hortfrei = Child::query()
            ->activeOn($date)
            ->whereHas('weeklySchedules', fn ($q) => $q->whereNotNull('planned_time'))
            ->whereDoesntHave('weeklySchedules', fn ($q) => $q->where('weekday', $weekday)->whereNotNull('planned_time'))
            ->whereNotIn('id', $absentChildIds)
            ->whereNotIn('id', $departures->pluck('child_id')->all())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Child $c) => [
                'id' => $c->id,
                'name' => $c->name,
                // Staff can jump to any child's plan; a parent only to their own.
                'can_manage' => $user->isStaff() || ($myChildIds?->contains($c->id) ?? false),
            ])
            ->values();

        // Companion-picker source for the day editor: each child's effective time today,
        // only for those who can actually be a companion (have a pickup, aren't away and
        // aren't themselves tagging along) — mirroring the Wochenplan.
        $pickerChildren = Child::query()->activeOn($date)->orderBy('name')->get(['id', 'name']);
        $pickerPlans = EffectivePlan::forMany($pickerChildren->pluck('id')->all(), [$date->toDateString()]);
        $companionChildren = $pickerChildren->map(function (Child $c) use ($pickerPlans, $date, $absentChildIds) {
            $plan = $pickerPlans[$c->id.'|'.$date->toDateString()] ?? null;
            $companionable = $plan
                && $plan['time'] !== null
                && $plan['method'] !== null
                && $plan['method'] !== DepartureMethod::WithChild->value
                && ! in_array($c->id, $absentChildIds, true);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'times' => $companionable ? [$date->toDateString() => $plan['time']] : [],
            ];
        })->values();

        return Inertia::render('Board/Index', [
            // Selected day + navigation meta (iso, label, is_today, editable, prev/next, offset).
            'date' => $day,
            'rows' => $rows,
            // Regularly not at the Hort today (Stammplan „Hortfrei"), for context.
            'hortfrei' => $hortfrei,
            // Parent-facing „geht mit … mit" summary for today (staff use the plan display).
            'companionNotes' => CompanionNotes::for($user, [$date->toDateString()]),
            'absent' => $absences->map(fn (Absence $a) => [
                'child_id' => $a->child_id,
                'name' => $a->child->name,
                'reason' => $a->reason->value,
                'reason_label' => $a->reason->label(),
                'comment' => $a->comment,
                'can_manage' => $user->isStaff() || ($myChildIds?->contains($a->child_id) ?? false),
            ])->values(),
            'excursions' => $excursionList,
            'program' => $hasProgram ? [
                'lunch' => $program?->lunch,
                'activity' => $program?->activity,
                'homework_start' => $homeworkStart ? substr((string) $homeworkStart, 0, 5) : null,
                'homework_end' => $homeworkEnd ? substr((string) $homeworkEnd, 0, 5) : null,
            ] : null,
            'canMark' => $user->isStaff() && $isToday,
            // The board uses the shared day editor (same as the Wochenplan), so it offers
            // the full method set incl. „geht mit … mit"; $children feeds its companion picker.
            'children' => $companionChildren,
            'methodOptions' => collect(DepartureMethod::cases())
                ->map(fn (DepartureMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->all(),
            'qualifierOptions' => collect(TimeQualifier::cases())
                ->map(fn (TimeQualifier $q) => ['value' => $q->value, 'label' => $q->label()])
                ->all(),
        ]);
    }

    /** Staff record (or undo) a child's departure. */
    public function mark(MarkDepartureRequest $request, DailyDeparture $departure): RedirectResponse
    {
        $this->authorize('mark', $departure);
        // Marking is a live, same-day action — never rewrite a past/future date.
        abort_unless($departure->date->isToday(), 403);

        $status = DepartureStatus::from($request->validated('status'));

        // Marking off (left_at) triggers the guardian Slack DM via the observer.
        $departure->update($status->hasLeft()
            ? ['status' => $status, 'left_at' => now(), 'marked_by' => $request->user()->id]
            : ['status' => $status, 'left_at' => null, 'marked_by' => null]);

        activity()
            ->causedBy($request->user())
            ->performedOn($departure)
            ->event($status->value)
            ->log($departure->child->name);

        return back();
    }

    /** Same-day change to the plan — by staff or the child's own parent. */
    public function override(OverrideDepartureRequest $request, DailyDeparture $departure): RedirectResponse
    {
        $this->authorize('update', $departure->child);
        // A board override is same-day only; future days edit via weekly-plan.adjust.
        abort_unless($departure->date->isToday(), 403);

        $validated = $request->validated();

        // A same-day board override always sets a concrete time, so any previous
        // companion arrangement no longer applies — clear it.
        // The qualifier only qualifies a „geht allein" time.
        $isSentHome = ($validated['planned_method'] ?? null) === DepartureMethod::SentHome->value;

        $departure->update([
            'planned_time' => $validated['planned_time'],
            'planned_method' => $validated['planned_method'] ?? null,
            'time_qualifier' => $isSentHome ? ($validated['time_qualifier'] ?? null) : null,
            'note' => $validated['note'] ?? null,
            'companion_child_id' => null,
            'companion_confirmed' => null,
            'companion_confirmed_by' => null,
            'companion_confirmed_at' => null,
        ]);

        // This child may be another child's companion — re-evaluate those arrangements
        // (e.g. the override just switched them to going home alone).
        CompanionReconciler::reconcile($departure->child_id, $departure->date->toDateString());

        return back()->with('status', __('flash.plan_updated', ['name' => $departure->child->name]));
    }
}
