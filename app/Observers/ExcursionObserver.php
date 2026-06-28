<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\AnnounceExcursionRsvp;
use App\Models\Excursion;
use App\Models\User;
use App\Notifications\NewExcursion;
use App\Services\SlackRsvp;
use Illuminate\Support\Facades\Notification;

class ExcursionObserver
{
    public function __construct(private SlackRsvp $slack) {}

    /** Announce a newly created excursion: interactive Slack DM + a web-push to guardians. */
    public function created(Excursion $excursion): void
    {
        AnnounceExcursionRsvp::dispatch($excursion);

        Notification::send(
            User::guardians()->whereHas('pushSubscriptions')->get(),
            new NewExcursion($excursion),
        );
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
