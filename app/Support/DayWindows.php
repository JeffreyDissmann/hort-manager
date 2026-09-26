<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HolidayCareDay;
use App\Models\HomeworkDefault;

/**
 * The timed windows of one Hort day — Hausaufgaben, a timed Aktivität, an Ausflug, the
 * Betreuungszeit of a Ferienbetreuung — and the one test everything asks of them: does
 * a pickup fall inside?
 *
 * The rule itself (half-open `[from, to)`, so a pickup at the very end of the
 * Hausaufgaben is fine) was written out eleven times across the app, and it drifted:
 * the Ferien-Hausaufgaben leak existed in exactly one copy. This class holds the test
 * and the window shapes; the callers keep their own loading, because the board and the
 * standing summary batch their queries while the Slack assistant looks up a single day.
 *
 * Windows are `array{kind: string, name: ?string, from: string, to: string,
 * from_default: bool}`; `from_default` marks a Hausaufgabenzeit that comes from the
 * weekday default rather than from the day's own program.
 */
class DayWindows
{
    /** Half-open containment: `$from <= $time < $to`. `H:i` strings compare directly. */
    public static function contains(?string $time, ?string $from, ?string $to): bool
    {
        if ($time === null || $from === null || $to === null) {
            return false;
        }

        return $time >= self::short($from) && $time < self::short($to);
    }

    /**
     * The day's program windows: the effective Hausaufgabenzeit and a timed Aktivität.
     * A Ferienbetreuung day has no Hausaufgaben at all — the per-weekday default knows
     * nothing about dates, so it has to be dropped here rather than in each caller.
     *
     * @return list<array{kind: string, name: ?string, from: string, to: string, from_default: bool}>
     */
    public static function program(?DailyProgram $program, ?HomeworkDefault $default, bool $isCareDay = false): array
    {
        $windows = [];

        if (! $isCareDay) {
            [$start, $end] = DailyProgram::effectiveHomework($program, $default);

            if ($start && $end) {
                $windows[] = [
                    'kind' => 'homework',
                    'name' => null,
                    'from' => self::short($start),
                    'to' => self::short($end),
                    'from_default' => ! $program?->homework_start,
                ];
            }
        }

        if ($program?->activity && $program->activity_start && $program->activity_end) {
            $windows[] = [
                'kind' => 'activity',
                'name' => $program->activity,
                'from' => self::short($program->activity_start),
                'to' => self::short($program->activity_end),
                'from_default' => false,
            ];
        }

        return $windows;
    }

    /**
     * The window a trip is away for, or null when it can't be judged: without a return
     * time nobody knows when the group is back, so no pickup can be called a clash. A
     * missing departure time counts as „from the morning on".
     *
     * @return array{kind: string, name: ?string, from: string, to: string, from_default: bool}|null
     */
    public static function excursion(?Excursion $excursion): ?array
    {
        if ($excursion === null || ! $excursion->return_at) {
            return null;
        }

        return [
            'kind' => 'excursion',
            'name' => $excursion->name,
            'from' => self::short($excursion->depart_at) ?? '00:00',
            'to' => self::short($excursion->return_at),
            'from_default' => false,
        ];
    }

    /**
     * The Betreuungszeit of a Ferienbetreuung day — the one window a pickup has to sit
     * *inside*, since that is when the Hort is staffed. Callers negate the test.
     *
     * @return array{kind: string, name: ?string, from: string, to: string, from_default: bool}|null
     */
    public static function care(?HolidayCareDay $day): ?array
    {
        $from = self::short($day?->starts_at);
        $to = self::short($day?->ends_at);

        if ($from === null || $to === null) {
            return null;
        }

        return [
            'kind' => 'care',
            'name' => $day->period?->name,
            'from' => $from,
            'to' => $to,
            'from_default' => false,
        ];
    }

    /**
     * Every window the time falls into, in the order given.
     *
     * @param  list<array{kind: string, name: ?string, from: string, to: string, from_default: bool}>  $windows
     * @return list<array{kind: string, name: ?string, from: string, to: string, from_default: bool}>
     */
    public static function hits(?string $time, array $windows): array
    {
        return array_values(array_filter(
            $windows,
            fn (array $window): bool => self::contains($time, $window['from'], $window['to']),
        ));
    }

    private static function short(mixed $time): ?string
    {
        return $time ? substr((string) $time, 0, 5) : null;
    }
}
