<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use Illuminate\Support\Carbon;

/**
 * The next day the Hort is actually open — the one thing a family wants from a closed
 * day. „Offen" means either an ordinary Hort day (a weekday nobody has closed) or a
 * Ferienbetreuung day, which is open by definition: someone offered it.
 *
 * A Schließzeit can run into a weekend, into the next Schließzeit, or straight into a
 * Ferienbetreuung, so the answer can't be „ends_on + 1 day".
 */
class NextHortDay
{
    /** How far ahead to look. Beyond a term's worth of closure there is no answer to give. */
    private const HORIZON_DAYS = 120;

    /**
     * @return array{date: string, care: string|null}|null null when nothing is open
     *                                                     inside the horizon
     */
    public static function after(Carbon $date): ?array
    {
        $from = $date->copy()->addDay()->startOfDay();
        $to = $from->copy()->addDays(self::HORIZON_DAYS);

        // Both lookups in one query each: walking day by day would otherwise cost two
        // queries per day of a two-week Schließzeit.
        $closed = HolidayPeriod::closedDaysBetween($from, $to);
        $careDays = HolidayCareDay::betweenKeyed($from, $to);

        for ($day = $from; $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();

            // A Schließzeit outranks a care day — the Hort being shut is the stronger
            // statement, and the board says the same.
            if (isset($closed[$key])) {
                continue;
            }

            if ($careDay = $careDays->get($key)) {
                return ['date' => $key, 'care' => $careDay->period->name];
            }

            if ($day->isWeekday()) {
                return ['date' => $key, 'care' => null];
            }
        }

        return null;
    }
}
