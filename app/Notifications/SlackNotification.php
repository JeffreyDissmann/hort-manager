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

        // A bot token alone isn't enough: without a Slack id there is nobody to DM, and
        // SlackChannel would throw („Slack notification channel is not set") once the
        // queued job runs. Users who never signed in via Slack simply skip the channel.
        $slackRoute = method_exists($notifiable, 'routeNotificationForSlack')
            ? $notifiable->routeNotificationForSlack($this)
            : null;

        if (config('services.slack.notifications.bot_user_oauth_token')
            && filled($slackRoute)
            && $wants('slack')) {
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
