<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\HolidayPeriod;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Announces a newly published Ferienbetreuung to the guardians, the way a new Ausflug
 * is announced — waiting for the Anmeldeschluss reminder would leave weeks of silence.
 */
class CareRegistrationOpened extends SlackNotification
{
    public function __construct(public HolidayPeriod $period) {}

    public function category(): string
    {
        return NotificationCategory::CareRegistration->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text($this->line())
            ->sectionBlock(function (SectionBlock $block) {
                $block->text("🏖️ *{$this->period->name}*\n{$this->line()}")->markdown();
            })
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Tage auswählen')->url(route('slack.enter', ['to' => 'care']));
            });
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Ferienbetreuung')
            ->body('🏖️ '.$this->line())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('care.index')]);
    }

    private function line(): string
    {
        $range = $this->period->starts_on->format('d.m.').'–'.$this->period->ends_on->format('d.m.Y');
        $deadline = $this->period->registration_deadline?->format('d.m.');

        $ask = $deadline === null
            ? 'Bitte trag ein, an welchen Tagen dein Kind dabei ist.'
            : "Bitte bis zum {$deadline} eintragen, an welchen Tagen dein Kind dabei ist.";

        return "Die Anmeldung für {$range} ist offen. {$ask}";
    }
}
