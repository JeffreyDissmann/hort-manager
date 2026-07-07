<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DailyDeparture;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Web-push to the companion child's guardians when another child is set to go home
 * with theirs and the companion goes home alone — their confirmation is required.
 * (Slack gets its own interactive Ja/Nein DM separately.)
 */
class CompanionRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DailyDeparture $departure) {}

    /** @return array<int, class-string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $child = $this->departure->child->name;
        $companion = $this->departure->companion->name;
        $date = $this->departure->date->format('d.m.');

        return (new WebPushMessage)
            ->title('Mitgehen bestätigen')
            ->body("{$child} möchte am {$date} mit {$companion} mit nach Hause. Bitte bestätigen.")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('weekly-plan')]);
    }
}
