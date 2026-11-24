<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\StandbySwap;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/** Tells the Springer-Koordination that two families exchanged their dates. */
class StandbySwapCompleted extends SlackNotification
{
    public function __construct(public StandbySwap $swap) {}

    public function category(): string
    {
        return NotificationCategory::StandbySwaps->value;
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
            ->title('Springerdienst getauscht')
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('standby.index')]);
    }

    private function body(): string
    {
        $swap = $this->swap->loadMissing(['offeredDuty', 'wantedDuty', 'offeredChild', 'wantedChild']);

        return sprintf(
            'Zwei Familien haben ihren Springerdienst getauscht: %s ist jetzt am %s eingeteilt (bisher %s), %s am %s (bisher %s).',
            $swap->offeredChild->name,
            StandbySwapRequested::day($swap->wantedDuty->date),
            $swap->wantedChild->name,
            $swap->wantedChild->name,
            StandbySwapRequested::day($swap->offeredDuty->date),
            $swap->offeredChild->name,
        );
    }
}
