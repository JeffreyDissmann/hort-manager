<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AbsenceReason;
use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Backfills a plausible past out of the Stammpläne that already exist, so „Statistik"
 * has something to count. Every weekday from `MONTHS_BACK` months ago until yesterday
 * gets the day it would have had: the planned pickup per scheduled child, the odd
 * change of plan, the odd sick day.
 *
 * Non-destructive and idempotent (`firstOrCreate` per child and date), and events are
 * off — otherwise a few thousand rows would each announce a departure and write an
 * activity-log entry. Run:
 *
 *   sail artisan db:seed --class=HistorySeeder
 */
class HistorySeeder extends Seeder
{
    use WithoutModelEvents;

    private const MONTHS_BACK = 6;

    /** Percent of days where the plan was changed on the day. */
    private const OVERRIDE_CHANCE = 12;

    /** Percent of days a child was reported sick or away instead of coming. */
    private const ABSENCE_CHANCE = 6;

    public function run(): void
    {
        $children = Child::with('weeklySchedules')->get();

        if ($children->isEmpty()) {
            $this->command?->warn('No children — run the main seeder first.');

            return;
        }

        $date = Carbon::today()->subMonths(self::MONTHS_BACK);
        $end = Carbon::yesterday();
        $departures = 0;
        $absences = 0;

        for (; $date->lte($end); $date->addDay()) {
            // No school day, no Hort day. A Ferienbetreuung is left alone: its rows are
            // sign-ups, and inventing them would put children in a period nobody
            // registered them for.
            if ($date->isWeekend() || HolidayPeriod::closesOn($date)) {
                continue;
            }

            foreach ($children as $child) {
                if (! $child->isActiveOn($date)) {
                    continue;
                }

                $schedule = $child->weeklySchedules
                    ->firstWhere('weekday', $date->dayOfWeekIso);

                // No Stammplan entry for that weekday = hortfrei, so no row at all.
                if (! $schedule?->planned_time) {
                    continue;
                }

                if (random_int(1, 100) <= self::ABSENCE_CHANCE) {
                    $absences += $this->reportAway($child, $date);

                    continue;
                }

                $departures += $this->recordDay($child, $date, (string) $schedule->planned_time, $schedule);
            }
        }

        $this->command?->info("History: {$departures} Abholungen, {$absences} Abwesenheiten.");
    }

    /** The day as it happened: planned from the Stammplan, sometimes moved, then left. */
    private function recordDay(Child $child, Carbon $date, string $plannedTime, $schedule): int
    {
        $time = random_int(1, 100) <= self::OVERRIDE_CHANCE
            ? $this->shift($plannedTime, random_int(-2, 2) * 30)
            : $plannedTime;

        $departure = DailyDeparture::firstOrCreate(
            ['child_id' => $child->id, 'date' => $date->toDateString()],
            [
                'planned_time' => $time,
                'planned_method' => $schedule->method,
                'time_qualifier' => $schedule->time_qualifier,
                // Children rarely leave exactly on the minute — and staff mark them off
                // a little after, which is what `left_at` actually records.
                'left_at' => $date->copy()->setTimeFromTimeString($this->shift($time, random_int(-5, 20))),
                'status' => $schedule->method?->value === 'sent_home'
                    ? DepartureStatus::SentHome
                    : DepartureStatus::PickedUp,
            ],
        );

        return $departure->wasRecentlyCreated ? 1 : 0;
    }

    private function reportAway(Child $child, Carbon $date): int
    {
        $sick = random_int(1, 100) <= 70; // most absences are illness

        $absence = Absence::firstOrCreate(
            ['child_id' => $child->id, 'date' => $date->toDateString()],
            [
                'reason' => $sick ? AbsenceReason::Sick : AbsenceReason::Away,
                'comment' => $sick ? 'krank' : 'Termin',
            ],
        );

        return $absence->wasRecentlyCreated ? 1 : 0;
    }

    /** „15:00" + 30 → „15:30", clamped to the Hort's opening hours. */
    private function shift(string $time, int $minutes): string
    {
        // `planned_time` comes back as „15:00" from some casts and „15:00:00" from
        // others, so parse rather than assume a format.
        $shifted = Carbon::createFromTimeString(substr($time, 0, 5))->addMinutes($minutes);

        // Keep it inside the Hort's day — a shift must not invent an 07:30 pickup.
        $minutesOfDay = min(max($shifted->hour * 60 + $shifted->minute, 12 * 60), 17 * 60);

        return sprintf('%02d:%02d', intdiv($minutesOfDay, 60), $minutesOfDay % 60);
    }
}
