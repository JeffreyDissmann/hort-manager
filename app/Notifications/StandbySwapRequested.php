<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\StandbySwap;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Asks the guardians of the requested date's child whether they'll swap — as a web
 * push. The Slack side is the interactive Annehmen/Ablehnen DM from SlackStandbySwap
 * (it has to be remembered to update it later), so this notification skips Slack.
 */
class StandbySwapRequested extends SlackNotification
{
    public function __construct(public StandbySwap $swap) {}

    public function category(): string
    {
        return NotificationCategory::Standby->value;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return array_values(array_diff(parent::via($notifiable), ['slack']));
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Springerdienst: Tauschanfrage')
            ->body(self::describe($this->swap))
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('standby.index')]);
    }

    /** The request, worded for the family that is asked. */
    public static function describe(StandbySwap $swap): string
    {
        $swap->loadMissing(['offeredDuty', 'wantedDuty', 'offeredChild', 'wantedChild']);

        return sprintf(
            'Die Familie von %s bittet um einen Springerdienst-Tausch: Ihr übernehmt den %s, die Familie von %s dafür euren %s (bisher %s). Getauscht wird erst, wenn ihr zustimmt.',
            $swap->offeredChild->name,
            self::day($swap->offeredDuty->date),
            $swap->offeredChild->name,
            self::day($swap->wantedDuty->date),
            $swap->wantedChild->name,
        );
    }

    /** „Di 3.3." — short enough for a push line. */
    public static function day(Carbon $date): string
    {
        return $date->copy()->locale('de')->isoFormat('dd D.M.');
    }
}
