<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Excursion;
use App\Services\SlackRsvp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Post the RSVP DM to every Slack-connected guardian, off the web request. */
class AnnounceExcursionRsvp implements ShouldQueue
{
    use Queueable;

    public function __construct(public Excursion $excursion) {}

    public function handle(SlackRsvp $slack): void
    {
        $slack->announce($this->excursion);
    }
}
