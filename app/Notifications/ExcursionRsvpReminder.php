<?php

namespace App\Notifications;

use App\Models\Excursion;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

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
}
