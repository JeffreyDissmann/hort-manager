<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesWeek;
use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\HomeworkDefault;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DailyProgramController extends Controller
{
    use ResolvesWeek;

    /** Staff weekly editor for the day program (lunch, activity, homework). */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DailyProgram::class);

        [$week, $weekDays] = $this->resolveWeek($request);

        $programs = DailyProgram::query()
            ->whereIn('date', $weekDays->pluck('date'))
            ->get()
            ->keyBy(fn (DailyProgram $p) => $p->date->toDateString());

        $defaults = HomeworkDefault::all()->keyBy('weekday');
        $weekRange = $weekDays->pluck('date');
        $children = Child::query()->activeBetween($weekRange->first(), $weekRange->last())
            ->whereNotNull('date_of_birth')->get(['id', 'name', 'date_of_birth']);

        $closedDays = HolidayPeriod::closedDaysBetween($weekRange->first(), $weekRange->last());

        // Ferienbetreuung days in this week, by date. Their Betreuungszeit is edited
        // here alongside the day's content — the period itself lives on /closures.
        $careDays = HolidayCareDay::query()
            ->whereBetween('date', [$weekRange->first(), $weekRange->last()])
            ->with('period:id,name')
            ->get()
            ->keyBy(fn (HolidayCareDay $day): string => $day->date->toDateString());

        $days = $weekDays->map(function (array $day) use ($programs, $defaults, $children, $closedDays, $careDays) {
            $weekday = Carbon::parse($day['date'])->dayOfWeekIso;
            $default = $defaults->get($weekday);
            $program = $programs->get($day['date']);
            $care = $careDays->get($day['date']);
            [$homeworkStart, $homeworkEnd] = $care
                ? [null, null] // Ferienbetreuung: no school, so no homework slot.
                : DailyProgram::effectiveHomework($program, $default);

            return [
                'date' => $day['date'],
                'label' => $day['label'],
                'date_label' => $day['date_label'],
                // Schließzeit: no food, no activity, no homework to fill in.
                'closed' => $closedDays[$day['date']] ?? null,
                // Ferienbetreuung: no school, so no homework — but there is a
                // Betreuungszeit to set, and usually an Aktivität.
                'care' => $care ? [
                    'id' => $care->id,
                    'name' => $care->period->name,
                    'starts_at' => HolidayCareDay::short($care->starts_at),
                    'ends_at' => HolidayCareDay::short($care->ends_at),
                ] : null,
                'lunch' => $program?->lunch,
                'activity' => $program?->activity,
                // Effective homework slot (override, else weekday default, else none).
                'homework_start' => $this->short($homeworkStart),
                'homework_end' => $this->short($homeworkEnd),
                // The weekday default, so the editor can restore it when unchecking "keine".
                'default_start' => $this->short($default?->start_time),
                'default_end' => $this->short($default?->end_time),
                // Children with a birthday on this day, so staff see it while filling out.
                'birthdays' => $children
                    ->filter(fn (Child $c) => $c->date_of_birth->format('m-d') === substr($day['date'], 5))
                    ->map(fn (Child $c) => [
                        'name' => $c->name,
                        'turns' => ((int) substr($day['date'], 0, 4)) - $c->date_of_birth->year,
                    ])
                    ->values(),
            ];
        });

        $homeworkDefaults = collect(range(1, 5))->map(fn (int $weekday) => [
            'weekday' => $weekday,
            'label' => self::WEEKDAY_LABELS[$weekday - 1],
            'start' => $this->short($defaults->get($weekday)?->start_time),
            'end' => $this->short($defaults->get($weekday)?->end_time),
        ]);

        return Inertia::render('Program/Index', [
            'week' => $week,
            'days' => $days,
            'homeworkDefaults' => $homeworkDefaults,
            'lateChangeCutoff' => Setting::lateChangeCutoff(),
            'weeklyDigestTime' => Setting::weeklyDigestTime(),
            'programReminderTime' => Setting::programReminderTime(),
            'programReminderLeadMinutes' => Setting::ProgramReminderLeadMinutes,
            // The times a newly offered Ferienbetreuung day starts out with.
            'careDefaultWindow' => array_combine(['start', 'end'], Setting::careDefaultWindow()),
        ]);
    }

    /** Save the whole week (lunch, activity, homework override). */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', DailyProgram::class);

        $validated = $request->validate([
            'days' => ['array'],
            'days.*.date' => ['required', 'date'],
            'days.*.lunch' => ['nullable', 'string', 'max:255'],
            'days.*.activity' => ['nullable', 'string', 'max:255'],
            'days.*.homework_start' => ['nullable', 'date_format:H:i'],
            'days.*.homework_end' => ['nullable', 'date_format:H:i'],
            'days.*.homework_none' => ['boolean'],
            // Ferienbetreuung days carry their Betreuungszeit alongside the content.
            'days.*.care_starts_at' => ['nullable', 'date_format:H:i'],
            'days.*.care_ends_at' => ['nullable', 'date_format:H:i', 'after:days.*.care_starts_at'],
        ]);

        $defaults = HomeworkDefault::all()->keyBy('weekday');
        $careDays = HolidayCareDay::query()
            ->whereIn('date', collect($validated['days'] ?? [])->pluck('date'))
            ->get()
            ->keyBy(fn (HolidayCareDay $day): string => $day->date->toDateString());

        foreach ($validated['days'] ?? [] as $row) {
            // A closed day has no program. Drop any row that exists (e.g. entered
            // before the Schließzeit was) rather than silently keeping it around.
            if (HolidayPeriod::closesOn($row['date'])) {
                DailyProgram::where('date', $row['date'])->delete();

                continue;
            }

            $weekday = Carbon::parse($row['date'])->dayOfWeekIso;
            $default = $defaults->get($weekday);
            $care = $careDays->get($row['date']);

            if ($care && ! empty($row['care_starts_at']) && ! empty($row['care_ends_at'])) {
                $care->update([
                    'starts_at' => $row['care_starts_at'],
                    'ends_at' => $row['care_ends_at'],
                ]);
            }

            if ($care) {
                // Ferienbetreuung: no school, so no homework to store or suppress.
                $homeworkNone = false;
                $homeworkStart = null;
                $homeworkEnd = null;
            } elseif ($row['homework_none'] ?? false) {
                // "Keine Hausaufgaben" — only needs storing when it suppresses a default.
                $homeworkNone = $default !== null;
                $homeworkStart = null;
                $homeworkEnd = null;
            } else {
                $homeworkNone = false;
                $homeworkStart = $row['homework_start'] ?? null;
                $homeworkEnd = $row['homework_end'] ?? null;

                // Equal to the weekday default → no override, so it keeps following it.
                if ($homeworkStart === $this->short($default?->start_time)
                    && $homeworkEnd === $this->short($default?->end_time)) {
                    $homeworkStart = null;
                    $homeworkEnd = null;
                }
            }

            $hasContent = ! empty($row['lunch']) || ! empty($row['activity'])
                || $homeworkStart !== null || $homeworkEnd !== null || $homeworkNone;

            if (! $hasContent) {
                DailyProgram::where('date', $row['date'])->delete();

                continue;
            }

            DailyProgram::updateOrCreate(
                ['date' => $row['date']],
                [
                    'lunch' => $row['lunch'] ?? null,
                    'activity' => $row['activity'] ?? null,
                    'homework_start' => $homeworkStart,
                    'homework_end' => $homeworkEnd,
                    'homework_none' => $homeworkNone,
                ],
            );
        }

        return back()->with('status', __('flash.program_saved'));
    }

    /** Save the Hort-wide default homework slots (per weekday). */
    public function updateDefaults(Request $request): RedirectResponse
    {
        $this->authorize('update', DailyProgram::class);

        $validated = $request->validate([
            'defaults' => ['array'],
            'defaults.*.weekday' => ['required', 'integer', 'between:1,5'],
            'defaults.*.start' => ['nullable', 'date_format:H:i'],
            'defaults.*.end' => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($validated['defaults'] ?? [] as $row) {
            if (empty($row['start']) && empty($row['end'])) {
                // Model delete (not a bulk query) so the removal is logged.
                HomeworkDefault::where('weekday', $row['weekday'])->first()?->delete();

                continue;
            }

            HomeworkDefault::updateOrCreate(
                ['weekday' => $row['weekday']],
                ['start_time' => $row['start'] ?? null, 'end_time' => $row['end'] ?? null],
            );
        }

        return back()->with('status', __('flash.homework_defaults_saved'));
    }

    /** Save the Hort-wide settings edited on this page (the late-change cutoff). */
    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('update', DailyProgram::class);

        $validated = $request->validate([
            'late_change_cutoff' => ['required', 'date_format:H:i'],
        ]);

        Setting::set(Setting::LateChangeCutoff, $validated['late_change_cutoff']);

        return back()->with('status', __('flash.settings_saved'));
    }

    /** Save when the Monday Wochenüberblick goes out to parents. */
    public function updateDigestTime(Request $request): RedirectResponse
    {
        $this->authorize('update', DailyProgram::class);

        $validated = $request->validate([
            // Staff are reminded ProgramReminderLeadMinutes earlier, so the digest
            // can't run before that or the reminder falls into the previous day.
            'weekly_digest_time' => ['required', 'date_format:H:i', 'after_or_equal:00:30'],
        ]);

        Setting::set(Setting::WeeklyDigestTime, $validated['weekly_digest_time']);

        return back()->with('status', __('flash.settings_saved'));
    }

    /**
     * Save the default Betreuungszeit a new Ferienbetreuung day starts out with.
     * Days already offered keep their times — this is a starting point, not a rule.
     */
    public function updateCareWindow(Request $request): RedirectResponse
    {
        $this->authorize('update', DailyProgram::class);

        $validated = $request->validate([
            'care_default_start' => ['required', 'date_format:H:i'],
            'care_default_end' => ['required', 'date_format:H:i', 'after:care_default_start'],
        ]);

        Setting::set(Setting::CareDefaultStart, $validated['care_default_start']);
        Setting::set(Setting::CareDefaultEnd, $validated['care_default_end']);

        return back()->with('status', __('flash.settings_saved'));
    }

    private function short(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
