<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\DailyDeparture;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells the *requesting* child's guardians once the companion's family has answered —
 * whether the „geht mit … mit" became the plan (confirmed) or their child stays a
 * normal pickup at the synced time (declined). Sent on Slack and/or web-push.
 */
class CompanionAnswered extends SlackNotification
{
    public function __construct(public DailyDeparture $departure, public bool $confirmed) {}

    public function category(): string
    {
        return NotificationCategory::Companion->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $body = $this->body();

        return (new SlackMessage)
            ->text($body)
            ->sectionBlock(fn (SectionBlock $block) => $block->text($body)->markdown())
            ->actionsBlock(fn (ActionsBlock $block) => $block
                ->button('Im Hort-Manager öffnen')
                ->url(route('slack.enter', ['to' => 'weekly-plan'])));
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Antwort zum Mitgehen')
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('weekly-plan')]);
    }

    private function body(): string
    {
        $child = $this->departure->child->name;
        $companion = $this->departure->companion->name;
        $date = $this->departure->date->format('d.m.');

        return $this->confirmed
            ? "{$companion}s Familie hat zugestimmt: {$child} geht am {$date} mit {$companion} mit."
            : "{$companion}s Familie hat abgesagt. {$child} wird am {$date} wie gewohnt abgeholt.";
    }
}
