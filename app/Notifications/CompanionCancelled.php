<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Web-push to the requesting child's guardians when a „geht mit … mit" arrangement
 * falls through because the companion is reported away — their child no longer has a
 * way home and needs a fresh pickup plan. Built from plain values (not the model),
 * because the reverted arrangement row is gone by the time this is sent.
 */
class CompanionCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $childName,
        public string $companionName,
        public string $date,
    ) {}

    /** @return array<int, class-string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Mitgehen nicht möglich')
            ->body("{$this->companionName} ist am {$this->date} nicht da. Bitte die Abholung für {$this->childName} neu planen.")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('weekly-plan')]);
    }
}
