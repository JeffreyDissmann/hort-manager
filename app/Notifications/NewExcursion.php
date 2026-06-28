<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Excursion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Web-push to guardians when a new excursion is created. (Slack gets the richer
 * interactive Ja/Nein DM separately, via the SlackRsvp service.)
 */
class NewExcursion extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Excursion $excursion) {}

    /** @return array<int, class-string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $date = $this->excursion->date->format('d.m.Y');

        return (new WebPushMessage)
            ->title('Neuer Ausflug 🚌')
            ->body("{$this->excursion->name} am {$date}. Bitte für dein Kind abstimmen.")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('polls.index')]);
    }
}
