<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\AnnounceExcursionRsvp;
use App\Models\Excursion;
use App\Services\SlackRsvp;

class ExcursionObserver
{
    public function __construct(private SlackRsvp $slack) {}

    /** Announce a newly created excursion (RSVP DM) to every Slack-connected guardian. */
    public function created(Excursion $excursion): void
    {
        AnnounceExcursionRsvp::dispatch($excursion);
    }

    /**
     * Cancel the DMs synchronously: this fires before deletion, while the tracked
     * messages still exist (they cascade away with the excursion), so it can't be
     * deferred to a job that would run after they're gone.
     */
    public function deleting(Excursion $excursion): void
    {
        $this->slack->cancel($excursion);
    }
}
