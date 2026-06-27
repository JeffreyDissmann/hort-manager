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
        $excursion = $this->excursion;
        $date = $excursion->date->format('d.m.Y');
        $depart = $excursion->depart_at ? substr((string) $excursion->depart_at, 0, 5) : null;
        $return = $excursion->return_at ? substr((string) $excursion->return_at, 0, 5) : null;

        // Everything a parent needs to decide, right in the message.
        $lines = ["🚌 *Neuer Ausflug:* {$excursion->name}", "📅 {$date}"];
        if ($depart) {
            $lines[] = '🕐 '.$depart.($return ? "–{$return}" : '').' Uhr';
        }
        if ($excursion->note) {
            $lines[] = "📝 {$excursion->note}";
        }
        if ($excursion->rsvp_deadline) {
            $lines[] = '⏰ Rückmeldung bitte bis *'.$excursion->rsvp_deadline->format('d.m.Y').'*';
        }
        $details = implode("\n", $lines);

        $message = (new SlackMessage)
            ->text("Neuer Ausflug: {$excursion->name} am {$date}.")
            ->sectionBlock(fn (SectionBlock $block) => $block->text($details)->markdown());

        // A Ja/Nein pair per child this guardian is responsible for.
        foreach ($notifiable->children as $child) {
            $message
                ->sectionBlock(fn (SectionBlock $block) => $block->text("Kommt *{$child->name}* mit?")->markdown())
                ->actionsBlock(function (ActionsBlock $block) use ($child) {
                    $block->button('✅ Ja')->primary()
                        ->id("rsvp_{$child->id}_yes")
                        ->value("rsvp|{$this->excursion->id}|{$child->id}|1");
                    $block->button('❌ Nein')->danger()
                        ->id("rsvp_{$child->id}_no")
                        ->value("rsvp|{$this->excursion->id}|{$child->id}|0");
                });
        }

        return $message->dividerBlock()
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Im Hort-Manager öffnen')->url(route('polls.index'));
            });
    }
}
