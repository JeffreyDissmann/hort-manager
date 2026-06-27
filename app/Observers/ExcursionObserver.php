<?php

namespace App\Observers;

use App\Models\Excursion;
use App\Models\User;
use App\Notifications\ExcursionAnnounced;
use Illuminate\Support\Facades\Notification;

class ExcursionObserver
{
    /** Announce a newly created excursion to every Slack-connected guardian. */
    public function created(Excursion $excursion): void
    {
        Notification::send(
            User::guardians()->onSlack()->get(),
            new ExcursionAnnounced($excursion),
        );
    }
}
