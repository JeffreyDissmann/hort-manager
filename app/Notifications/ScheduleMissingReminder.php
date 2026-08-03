<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Child;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/** DMs a child's guardians that its Stammplan (weekly plan) still isn't set up. */
class ScheduleMissingReminder extends SlackNotification
{
    public function __construct(public Child $child) {}

    public function category(): string
    {
        return NotificationCategory::MissingPlan->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $name = $this->child->name;

        return (new SlackMessage)
            ->text("Für {$name} ist noch kein Wochenplan hinterlegt.")
            ->sectionBlock(function (SectionBlock $block) use ($name) {
                $block->text("🗓️ *Noch kein Wochenplan für {$name}.*\nBitte trag kurz ein, wann {$name} an welchen Tagen nach Hause geht.")->markdown();
            })
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Wochenplan eintragen')->url(route('slack.enter', ['to' => 'children']));
            });
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Hort-Manager')
            ->body("🗓️ Für {$this->child->name} ist noch kein Wochenplan hinterlegt.")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('children.index')]);
    }
}
