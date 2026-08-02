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
 * Reminds a guardian to answer an open Ferienbetreuung for the children of theirs
 * that still have no answer. „Keine Tage" counts as answered, so nobody who has
 * decided against it is chased.
 */
class CareRegistrationReminder extends SlackNotification
{
    /** @param  list<string>  $childNames */
    public function __construct(
        public HolidayPeriod $period,
        public array $childNames,
    ) {}

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
        $names = self::joinNames($this->childNames);
        $deadline = $this->period->registration_deadline?->format('d.m.');

        $when = $deadline === null
            ? 'Bitte trag ein, an welchen Tagen '.$names.' dabei ist.'
            : "Bitte bis zum {$deadline} eintragen, an welchen Tagen {$names} dabei ist.";

        return "Die Anmeldung läuft noch. {$when}";
    }

    /** @param  list<string>  $names */
    private static function joinNames(array $names): string
    {
        if (count($names) <= 1) {
            return $names[0] ?? '';
        }

        $last = array_pop($names);

        return implode(', ', $names).' und '.$last;
    }
}
