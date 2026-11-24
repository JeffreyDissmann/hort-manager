<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushMessage;

/** The afternoon before: „ihr seid am … für den Springerdienst eingeteilt". */
class StandbyReminder extends SlackNotification
{
    /** @param  list<string>  $children  the guardian's own children on standby that day */
    public function __construct(public Carbon $date, public array $children) {}

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
            ->title('Springerdienst: Erinnerung')
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('standby.index')]);
    }

    private function body(): string
    {
        return sprintf(
            'Erinnerung: Ihr seid am %s für den Springerdienst eingeteilt (%s). Fällt eine Erzieher:in aus, meldet sich das Team über Slack bei euch.',
            StandbySwapRequested::day($this->date),
            implode(', ', $this->children),
        );
    }
}
