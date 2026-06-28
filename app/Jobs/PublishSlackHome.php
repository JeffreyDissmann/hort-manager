<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SlackHome;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Publish the App Home tab for a Slack user, off the request handling the event. */
class PublishSlackHome implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $slackUserId) {}

    public function handle(SlackHome $home): void
    {
        $home->publish($this->slackUserId);
    }
}
