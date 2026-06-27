<?php

namespace App\Notifications;

use App\Models\Excursion;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

/** DMs guardians on Slack when a new excursion is created, so they can RSVP. */
class ExcursionAnnounced extends SlackNotification
{
    public function __construct(public Excursion $excursion) {}

    public function toSlack(object $notifiable): SlackMessage
    {
        $date = $this->excursion->date->format('d.m.Y');
        $deadline = $this->excursion->rsvp_deadline?->format('d.m.Y');
        $deadlineLine = $deadline ? "\nBitte gib bis *{$deadline}* eine Rückmeldung." : '';

        return (new SlackMessage)
            ->text("Neuer Ausflug: {$this->excursion->name} am {$date}.")
            ->sectionBlock(function (SectionBlock $block) use ($date, $deadlineLine) {
                $block->text("🚌 *Neuer Ausflug:* {$this->excursion->name}\nAm {$date}.{$deadlineLine}")->markdown();
            })
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Jetzt abstimmen')->url(route('polls.index'));
            });
    }
}
