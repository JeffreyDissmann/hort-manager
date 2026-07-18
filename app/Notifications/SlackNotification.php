<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/** Base for our Slack DMs — queued; also web-push when the subclass supports it. */
abstract class SlackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** The NotificationCategory value this notification belongs to (for per-user toggles). */
    abstract public function category(): string;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = [];
        $category = $this->category();
        $wants = fn (string $channel): bool => ! method_exists($notifiable, 'wantsNotification')
            || $notifiable->wantsNotification($category, $channel);

        if (config('services.slack.notifications.bot_user_oauth_token') && $wants('slack')) {
            $channels[] = 'slack';
        }

        // Also web-push if this notification provides a payload and the user opted in.
        if (method_exists($this, 'toWebPush')
            && method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists()
            && $wants('push')) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }
}
