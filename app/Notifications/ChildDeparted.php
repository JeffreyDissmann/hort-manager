<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\DepartureStatus;
use App\Models\DailyDeparture;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

/** DMs a child's guardians on Slack when staff mark the child off the board. */
class ChildDeparted extends SlackNotification
{
    public function __construct(public DailyDeparture $departure) {}

    public function toSlack(object $notifiable): SlackMessage
    {
        $name = $this->departure->child->name;
        $time = $this->departure->left_at?->format('H:i');

        $how = $this->departure->status === DepartureStatus::PickedUp
            ? "wurde um {$time} Uhr abgeholt"
            : "ist um {$time} Uhr allein nach Hause gegangen";

        return (new SlackMessage)
            ->text("{$name} {$how}.")
            ->sectionBlock(function (SectionBlock $block) use ($name, $how) {
                $block->text("👋 *{$name}* {$how}.")->markdown();
            })
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Im Hort-Manager öffnen')->url(route('board'));
            });
    }
}
