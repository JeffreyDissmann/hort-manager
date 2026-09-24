<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Excursion;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/** DMs guardians who still haven't answered an excursion poll due today. */
class ExcursionRsvpReminder extends SlackNotification
{
    public function __construct(public Excursion $excursion) {}

    public function category(): string
    {
        return NotificationCategory::Excursions->value;
    }

    /**
     * Three days in the life of one poll: the Anmeldeschluss itself, the days after it
     * (the reminder repeats daily while nobody has answered), and the morning of the
     * trip. The ask is the same; how urgent it is isn't.
     *
     * @return array{0: string, 1: string} headline and line
     */
    private function wording(): array
    {
        $date = $this->excursion->date->format('d.m.Y');
        $name = $this->excursion->name;

        if ($this->excursion->date->isToday()) {
            return ["🚌 *Heute ist der Ausflug:* {$name}.", 'Wir wissen noch nicht, ob dein Kind mitkommt – bitte sag kurz Bescheid.'];
        }

        if ($this->excursion->rsvp_deadline?->endOfDay()->isPast()) {
            return ["⏰ *Noch keine Rückmeldung:* {$name} (am {$date}).", 'Der Anmeldeschluss ist vorbei, deine Antwort zählt aber weiterhin.'];
        }

        return ["⏰ *Letzte Chance zur Rückmeldung:* {$name} (am {$date}).", 'Bitte sag uns heute noch, ob dein Kind mitkommt.'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        [$headline, $line] = $this->wording();

        return (new SlackMessage)
            ->text("Erinnerung: Bitte stimme für den Ausflug {$this->excursion->name} ab.")
            ->sectionBlock(function (SectionBlock $block) use ($headline, $line) {
                $block->text("{$headline}\n{$line}")->markdown();
            })
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Jetzt abstimmen')->url(route('polls.index'));
            });
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $prefix = match (true) {
            $this->excursion->date->isToday() => '🚌 Heute',
            (bool) $this->excursion->rsvp_deadline?->endOfDay()->isPast() => '⏰ Noch offen',
            default => '⏰ Letzte Chance',
        };

        return (new WebPushMessage)
            ->title('Hort-Manager')
            ->body("{$prefix}: Kommt dein Kind beim Ausflug „{$this->excursion->name}“ mit?")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('polls.index')]);
    }
}
