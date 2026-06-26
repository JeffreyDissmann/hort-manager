<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared Mo–Fr work-week resolution for the week-navigable views
 * (Abholplan, Tagesprogramm): reads ?week=YYYY-MM-DD, defaults to the
 * current week, and builds the navigation meta + the five weekdays.
 */
trait ResolvesWeek
{
    private const WEEKDAY_LABELS = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

    /**
     * @return array{0: array<string, mixed>, 1: Collection<int, array{date: string, label: string, date_label: string}>}
     */
    protected function resolveWeek(Request $request): array
    {
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        if ($request->filled('week')) {
            try {
                $weekStart = Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable) {
                // Invalid value → keep the current week.
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
