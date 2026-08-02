<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DailyDeparture;
use App\Models\HolidayCareDay;

class HolidayCareDayObserver
{
    /**
     * A day that is no longer offered takes its sign-ups with it. Registration *is*
     * the child's DailyDeparture, and that row has no foreign key to the care day, so
     * nothing would otherwise remove it — the children would linger on a date that
     * isn't offered any more, reappearing on the board as Stammplan overrides.
     *
     * A day already lived through keeps its rows: that is history, not a plan.
     */
    public function deleting(HolidayCareDay $careDay): void
    {
        // Only the sign-ups made *for this day*: a plan override entered before the
        // Ferienbetreuung existed, or a sign-up through another period offering the
        // same date, belongs to somebody else and must survive.
        DailyDeparture::query()
            ->where('holiday_care_day_id', $careDay->id)
            ->whereNull('left_at')
            ->delete();
    }
}
