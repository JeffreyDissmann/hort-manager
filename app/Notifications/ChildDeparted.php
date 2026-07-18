<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\DepartureStatus;
use App\Enums\NotificationCategory;
use App\Models\DailyDeparture;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/** DMs a child's guardians on Slack when staff mark the child off the board. */
class ChildDeparted extends SlackNotification
{
    public function __construct(public DailyDeparture $departure) {}

    public function category(): string
    {
        return NotificationCategory::Departures->value;
    }

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

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $name = $this->departure->child->name;
        $time = $this->departure->left_at?->format('H:i');

        $how = $this->departure->status === DepartureStatus::PickedUp
            ? "wurde um {$time} Uhr abgeholt"
            : "ist um {$time} Uhr allein nach Hause gegangen";

        return (new WebPushMessage)
            ->title('Hort-Manager')
            ->body("👋 {$name} {$how}.")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('board')]);
    }
}
