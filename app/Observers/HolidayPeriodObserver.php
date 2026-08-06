<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Notifications\CareRegistrationOpened;
use Illuminate\Support\Facades\Notification;

class HolidayPeriodObserver
{
    /**
     * Announce a new Ferienbetreuung to the guardians — the same beat as a new Ausflug.
     * A Schließzeit announces nothing: there is nothing to answer, and the „kommende
     * Schließzeiten" page plus the greyed-out days already carry it.
     */
    public function created(HolidayPeriod $period): void
    {
        if (! $period->isCare()) {
            $this->stopOfferingClosedDays($period);

            return;
        }

        Notification::send(
            User::query()->reachable()->guardians()->get(),
            new CareRegistrationOpened($period),
        );
    }

    /** A closure's range can move onto days a Ferienbetreuung already offers. */
    public function updated(HolidayPeriod $period): void
    {
        if (! $period->isCare()) {
            $this->stopOfferingClosedDays($period);

            return;
        }

        if ($period->wasChanged(['starts_on', 'ends_on'])) {
            $this->dropDaysOutsideRange($period);
        }
    }

    /**
     * A Ferienbetreuung that moves leaves the days of its old range behind. On the
     * sign-up sheet those looked like offered days of the *new* period — dates that
     * aren't even inside it. The edit form used to clean this up on its way through
     * the controller, so any other path (a seeder, tinker, a future import) produced
     * a period offering days it doesn't cover.
     *
     * Force-deleted, not soft: a day outside the range was never un-offered by staff,
     * and a tombstone would stop it coming back if the range moves back.
     */
    private function dropDaysOutsideRange(HolidayPeriod $period): void
    {
        $period->careDays()
            ->withTrashed()
            ->where(fn ($q) => $q->whereDate('date', '<', $period->starts_on)
                ->orWhereDate('date', '>', $period->ends_on))
            ->get()
            ->each
            ->forceDelete();
    }

    /**
     * A Schließzeit entered over a Ferienbetreuung wins — the Hort is shut, so those
     * days stop being offered and their sign-ups are withdrawn with them (see
     * HolidayCareDayObserver). Otherwise the children stay planned for a day the board
     * refuses to show, which nobody would notice until they didn't turn up.
     *
     * Deleting the closure again doesn't restore them; re-saving the Ferienbetreuung
     * does, since editing it re-offers any missing weekday. That's why these are
     * force-deleted: a tombstone would say „staff un-offered this day" and block it.
     */
    private function stopOfferingClosedDays(HolidayPeriod $closure): void
    {
        // Date strings, not Carbon: a cast Carbon renders as „Y-m-d H:i:s" and would
        // never match a `date` column between its own bounds.
        HolidayCareDay::query()
            ->whereBetween('date', [
                $closure->starts_on->toDateString(),
                $closure->ends_on->toDateString(),
            ])
            ->get()
            ->each
            ->forceDelete();
    }

    /**
     * Delete the offered days one by one so each withdraws its sign-ups (see
     * HolidayCareDayObserver). The database cascade would take the day rows without
     * ever firing a model event, leaving children planned for days nobody offers.
     */
    public function deleting(HolidayPeriod $period): void
    {
        $period->careDays()->withTrashed()->get()->each->forceDelete();
    }
}
