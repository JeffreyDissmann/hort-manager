<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells the requesting child's guardians when a „geht mit … mit" arrangement falls
 * through because the companion is reported away — their child no longer has a way
 * home and needs a fresh pickup plan. Sent on Slack and/or web-push. Built from plain
 * values (not the model), because the reverted arrangement row is gone by the time
 * this is sent.
 */
class CompanionCancelled extends SlackNotification
{
    public function __construct(
        public string $childName,
        public string $companionName,
        public string $date,
    ) {}

    public function category(): string
    {
        return NotificationCategory::Companion->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $body = $this->body();

        return (new SlackMessage)
            ->text($body)
            ->sectionBlock(fn (SectionBlock $block) => $block->text("⚠️ {$body}")->markdown())
            ->actionsBlock(fn (ActionsBlock $block) => $block
                ->button('Im Hort-Manager öffnen')
                ->url(route('slack.enter', ['to' => 'weekly-plan'])));
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Mitgehen nicht möglich')
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('weekly-plan')]);
    }

    private function body(): string
    {
        return "{$this->companionName} ist am {$this->date} nicht da. Bitte die Abholung für {$this->childName} neu planen.";
    }
}
