<?php

namespace App\Http\Controllers;

use App\Models\DailyProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DailyProgramController extends Controller
{
    private const WEEKDAY_LABELS = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

    /** Staff weekly editor for the day program (lunch + activity). */
    public function index(Request $request): Response
    {
        $this->ensureStaff();

        [$week, $weekDays] = $this->resolveWeek($request);

        $programs = DailyProgram::query()
            ->whereIn('date', $weekDays->pluck('date'))
            ->get()
            ->keyBy(fn (DailyProgram $p) => $p->date->toDateString());

        $days = $weekDays->map(fn (array $day) => [
            'date' => $day['date'],
            'label' => $day['label'],
            'date_label' => $day['date_label'],
            'lunch' => $programs->get($day['date'])?->lunch,
            'activity' => $programs->get($day['date'])?->activity,
        ]);

        return Inertia::render('Program/Index', [
            'week' => $week,
            'days' => $days,
        ]);
    }

    /** Save the whole week at once. */
    public function update(Request $request): RedirectResponse
    {
        $this->ensureStaff();

        $validated = $request->validate([
            'days' => ['array'],
            'days.*.date' => ['required', 'date'],
            'days.*.lunch' => ['nullable', 'string', 'max:255'],
            'days.*.activity' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['days'] ?? [] as $row) {
            if (empty($row['lunch']) && empty($row['activity'])) {
                DailyProgram::where('date', $row['date'])->delete();

                continue;
            }

            DailyProgram::updateOrCreate(
                ['date' => $row['date']],
                ['lunch' => $row['lunch'] ?? null, 'activity' => $row['activity'] ?? null],
            );
        }

        return back()->with('status', 'Programm gespeichert.');
    }

    private function ensureStaff(): void
    {
        abort_unless(auth()->user()?->isStaff(), 403);
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
