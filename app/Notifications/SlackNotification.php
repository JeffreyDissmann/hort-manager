<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Base for our Slack DMs — delivers only when a bot token is configured. */
abstract class SlackNotification extends Notification
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return config('services.slack.notifications.bot_user_oauth_token') ? ['slack'] : [];
    }
}
