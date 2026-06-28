<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Excursion;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/** DMs guardians who still haven't answered an excursion poll due today. */
class ExcursionRsvpReminder extends SlackNotification
{
    public function __construct(public Excursion $excursion) {}

    public function toSlack(object $notifiable): SlackMessage
    {
        $date = $this->excursion->date->format('d.m.Y');

        return (new SlackMessage)
            ->text("Erinnerung: Bitte stimme für den Ausflug {$this->excursion->name} ab.")
            ->sectionBlock(function (SectionBlock $block) use ($date) {
                $block->text("⏰ *Letzte Chance zur Rückmeldung:* {$this->excursion->name} (am {$date}).\nBitte sag uns heute noch, ob dein Kind mitkommt.")->markdown();
            })
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Jetzt abstimmen')->url(route('polls.index'));
            });
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Hort-Manager')
            ->body("⏰ Letzte Chance: Kommt dein Kind beim Ausflug „{$this->excursion->name}“ mit?")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('polls.index')]);
    }
}
