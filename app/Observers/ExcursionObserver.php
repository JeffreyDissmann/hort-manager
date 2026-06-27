<?php

namespace App\Observers;

use App\Models\Excursion;
use App\Services\SlackRsvp;

class ExcursionObserver
{
    public function __construct(private SlackRsvp $slack) {}

    /** Announce a newly created excursion (RSVP DM) to every Slack-connected guardian. */
    public function created(Excursion $excursion): void
    {
        $this->slack->announce($excursion);
    }

    /** Before deletion (while the tracked messages still exist), mark the DMs cancelled. */
    public function deleting(Excursion $excursion): void
    {
        $this->slack->cancel($excursion);
    }
}
