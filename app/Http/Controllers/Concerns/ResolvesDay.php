<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared single-day resolution for the day-navigable Heute board: reads
 * ?date=YYYY-MM-DD, defaults to today (advancing across the weekend), keeps
 * navigation on weekdays (Mon–Fri), and clamps the past to the data-retention
 * floor so we never point at pruned/empty days. Returns the target date plus a
 * navigation meta object (prev/next weekday, relative offset, editability).
 */
trait ResolvesDay
{
    private const WEEKDAYS_DE = [
        1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch',
        4 => 'Donnerstag', 5 => 'Freitag',
    ];

    /**
     * @return array{0: Carbon, 1: array<string, mixed>}
     */
    protected function resolveDay(Request $request): array
    {
        $floor = $this->retentionFloor();
        $date = $this->nextWeekday(Carbon::today());

        if ($request->filled('date')) {
            try {
                $date = $this->nextWeekday(Carbon::parse($request->query('date'))->startOfDay());
            } catch (\Throwable) {
                // Invalid value → keep today.
            }
        }

        // Never earlier than what retention still keeps.
        if ($date->lt($floor)) {
            $date = $floor->copy();
        }

        $today = Carbon::today();
        $prev = $this->prevWeekday($date);

        $day = [
            'iso' => $date->toDateString(),
            'label' => self::WEEKDAYS_DE[$date->dayOfWeekIso].', '.$date->format('d.m.Y'),
            'is_today' => $date->isToday(),
            'is_future' => $date->gt($today),
            // Today and future weekdays are editable; the past is read-only history.
            'editable' => $date->gte($today),
            // Null disables the back chevron at the retention boundary.
            'prev' => $prev->gte($floor) ? $prev->toDateString() : null,
            'next' => $this->nextWeekday($date->copy()->addDay())->toDateString(),
            // Earliest selectable day (retention floor) — the date picker's min.
            'floor' => $floor->toDateString(),
            // Signed calendar-day offset from today (drives the relative label + tone).
            'offset' => (int) $today->diffInDays($date, false),
        ];

        return [$date, $day];
    }

    /** The earliest weekday that still has data (mirrors PruneOldData's cutoff). */
    private function retentionFloor(): Carbon
    {
        $weeks = (int) config('hort.retention_weeks');

        return $this->nextWeekday(now()->subWeeks($weeks)->startOfDay());
    }

    private function nextWeekday(Carbon $date): Carbon
    {
        $date = $date->copy();
        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }

    private function prevWeekday(Carbon $date): Carbon
    {
        $date = $date->copy()->subDay();
        while ($date->isWeekend()) {
            $date->subDay();
        }

        return $date;
    }
}
