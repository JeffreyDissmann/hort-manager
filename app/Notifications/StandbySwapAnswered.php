<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\StandbySwap;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/** Tells the asking family whether the other family agreed to the swap. */
class StandbySwapAnswered extends SlackNotification
{
    public function __construct(public StandbySwap $swap, public bool $accepted) {}

    public function category(): string
    {
        return NotificationCategory::Standby->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $body = $this->body();

        return (new SlackMessage)
            ->text($body)
            ->sectionBlock(fn (SectionBlock $block) => $block->text($body)->markdown())
            ->actionsBlock(fn (ActionsBlock $block) => $block
                ->button('Springerdienst ansehen')
                ->url(route('slack.enter', ['to' => 'standby'])));
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->accepted ? 'Springerdienst getauscht' : 'Springerdienst: Tausch abgelehnt')
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('standby.index')]);
    }

    private function body(): string
    {
        $swap = $this->swap->loadMissing(['offeredDuty', 'wantedDuty', 'offeredChild', 'wantedChild']);
        $wanted = StandbySwapRequested::day($swap->wantedDuty->date);
        $offered = StandbySwapRequested::day($swap->offeredDuty->date);

        return $this->accepted
            ? "Die Familie von {$swap->wantedChild->name} hat dem Tausch zugestimmt: Ihr seid jetzt am {$wanted} eingeteilt statt am {$offered}."
            : "Die Familie von {$swap->wantedChild->name} hat den Tausch abgelehnt. Ihr bleibt am {$offered} eingeteilt.";
    }
}
