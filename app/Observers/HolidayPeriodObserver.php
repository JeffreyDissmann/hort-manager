<?php

declare(strict_types=1);

namespace App\Observers;

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
            return;
        }

        Notification::send(
            User::query()->reachable()->guardians()->get(),
            new CareRegistrationOpened($period),
        );
    }
}
