<?php

namespace App\Http\Controllers;

use App\Models\DailyProgram;
use App\Models\HomeworkDefault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DailyProgramController extends Controller
{
    private const WEEKDAY_LABELS = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

    /** Staff weekly editor for the day program (lunch, activity, homework). */
    public function index(Request $request): Response
    {
        $this->ensureStaff();

        [$week, $weekDays] = $this->resolveWeek($request);

        $programs = DailyProgram::query()
            ->whereIn('date', $weekDays->pluck('date'))
            ->get()
            ->keyBy(fn (DailyProgram $p) => $p->date->toDateString());

        $defaults = HomeworkDefault::all()->keyBy('weekday');

        $days = $weekDays->map(function (array $day) use ($programs, $defaults) {
            $weekday = Carbon::parse($day['date'])->dayOfWeekIso;
            $default = $defaults->get($weekday);
            $program = $programs->get($day['date']);

            return [
                'date' => $day['date'],
                'label' => $day['label'],
                'date_label' => $day['date_label'],
                'lunch' => $program?->lunch,
                'activity' => $program?->activity,
                // Effective homework slot = per-date override, otherwise the weekday default.
                'homework_start' => $this->short($program?->homework_start ?? $default?->start_time),
                'homework_end' => $this->short($program?->homework_end ?? $default?->end_time),
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
        ]);
    }

    /** Save the whole week (lunch, activity, homework override). */
    public function update(Request $request): RedirectResponse
    {
        $this->ensureStaff();

        $validated = $request->validate([
            'days' => ['array'],
            'days.*.date' => ['required', 'date'],
            'days.*.lunch' => ['nullable', 'string', 'max:255'],
            'days.*.activity' => ['nullable', 'string', 'max:255'],
            'days.*.homework_start' => ['nullable', 'date_format:H:i'],
            'days.*.homework_end' => ['nullable', 'date_format:H:i'],
        ]);

        $defaults = HomeworkDefault::all()->keyBy('weekday');

        foreach ($validated['days'] ?? [] as $row) {
            $weekday = Carbon::parse($row['date'])->dayOfWeekIso;
            $default = $defaults->get($weekday);

            $homeworkStart = $row['homework_start'] ?? null;
            $homeworkEnd = $row['homework_end'] ?? null;

            // Equal to the weekday default → no override, so it keeps following the default.
            if ($homeworkStart === $this->short($default?->start_time)
                && $homeworkEnd === $this->short($default?->end_time)) {
                $homeworkStart = null;
                $homeworkEnd = null;
            }

            $hasContent = ! empty($row['lunch']) || ! empty($row['activity'])
                || $homeworkStart !== null || $homeworkEnd !== null;

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
                ],
            );
        }

        return back()->with('status', 'Programm gespeichert.');
    }

    /** Save the Hort-wide default homework slots (per weekday). */
    public function updateDefaults(Request $request): RedirectResponse
    {
        $this->ensureStaff();

        $validated = $request->validate([
            'defaults' => ['array'],
            'defaults.*.weekday' => ['required', 'integer', 'between:1,5'],
            'defaults.*.start' => ['nullable', 'date_format:H:i'],
            'defaults.*.end' => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($validated['defaults'] ?? [] as $row) {
            if (empty($row['start']) && empty($row['end'])) {
                HomeworkDefault::where('weekday', $row['weekday'])->delete();

                continue;
            }

            HomeworkDefault::updateOrCreate(
                ['weekday' => $row['weekday']],
                ['start_time' => $row['start'] ?? null, 'end_time' => $row['end'] ?? null],
            );
        }

        return back()->with('status', 'Standard-Hausaufgabenzeiten gespeichert.');
    }

    private function ensureStaff(): void
    {
        abort_unless(auth()->user()?->isStaff(), 403);
    }

    private function short(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    /**
     * @return array{0: array<string, mixed>, 1: Collection<int, array<string, string>>}
     */
    private function resolveWeek(Request $request): array
    {
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        if ($request->filled('week')) {
            try {
                $weekStart = Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable) {
                // keep current week
            }
        }

        $week = [
            'label' => 'KW '.$weekStart->isoWeek().' · '
                .$weekStart->format('d.m.').'–'.$weekStart->copy()->addDays(4)->format('d.m.'),
            'prev' => $weekStart->copy()->subWeek()->toDateString(),
            'next' => $weekStart->copy()->addWeek()->toDateString(),
            'is_current' => $weekStart->equalTo($today->copy()->startOfWeek(Carbon::MONDAY)),
        ];

        $weekDays = collect(range(0, 4))->map(function (int $i) use ($weekStart) {
            $date = $weekStart->copy()->addDays($i);

            return [
                'date' => $date->toDateString(),
                'label' => self::WEEKDAY_LABELS[$i],
                'date_label' => $date->format('d.m.'),
            ];
        });

        return [$week, $weekDays];
    }
}
