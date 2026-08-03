<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Child;
use App\Models\User;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/** DMs staff that a parent changed something about today after the cutoff time. */
class LateChange extends SlackNotification
{
    public function __construct(
        public Child $child,
        public User $actor,
        public string $summary,
    ) {}

    public function category(): string
    {
        return NotificationCategory::LateChange->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text($this->line())
            ->sectionBlock(function (SectionBlock $block) {
                $block->text("⏰ *Änderung für heute*\n{$this->line()}")->markdown();
            })
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Zum Tagesboard')->url(route('slack.enter', ['to' => 'board']));
            });
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Änderung für heute')
            ->body('⏰ '.$this->line())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('board')]);
    }

    private function line(): string
    {
        return "{$this->child->name}: {$this->summary} (geändert von {$this->actor->name})";
    }
}
