<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Base for our Slack DMs — queued, and delivered only when a bot token is configured. */
abstract class SlackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return config('services.slack.notifications.bot_user_oauth_token') ? ['slack'] : [];
    }
}
