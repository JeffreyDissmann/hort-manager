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
 * Web-push back to the *requesting* child's guardians once the companion's family has
 * answered — so they learn whether the „geht mit … mit" became the plan (confirmed) or
 * their child stays a normal pickup at the synced time (declined).
 */
class CompanionAnswered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DailyDeparture $departure, public bool $confirmed) {}

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

        $body = $this->confirmed
            ? "{$companion}s Familie hat zugestimmt: {$child} geht am {$date} mit {$companion} mit."
            : "{$companion}s Familie hat abgesagt. {$child} wird am {$date} wie gewohnt abgeholt.";

        return (new WebPushMessage)
            ->title('Antwort zum Mitgehen')
            ->body($body)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('weekly-plan')]);
    }
}
