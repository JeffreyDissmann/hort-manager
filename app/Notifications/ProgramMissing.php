<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Setting;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * DMs staff that this week's Tagesprogramm still has days without lunch — sent
 * `Setting::ProgramReminderLeadMinutes` before the parents' Wochenüberblick, so there
 * is still time to fill it in.
 */
class ProgramMissing extends SlackNotification
{
    /** @param  list<Carbon>  $missingDays */
    public function __construct(public array $missingDays) {}

    public function category(): string
    {
        return NotificationCategory::ProgramMissing->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text($this->line())
            ->sectionBlock(function (SectionBlock $block) {
                $block->text("🍽️ *Wochenprogramm noch nicht vollständig*\n{$this->line()}")->markdown();
            })
            ->actionsBlock(function (ActionsBlock $block) {
                $block->button('Programm ausfüllen')->url(route('slack.enter', ['to' => 'program']));
            });
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Wochenprogramm')
            ->body('🍽️ '.$this->line())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('program')]);
    }

    private function line(): string
    {
        $days = collect($this->missingDays)
            ->map(fn (Carbon $day): string => $day->locale('de')->isoFormat('dddd'))
            ->join(', ', ' und ');

        $lead = Setting::ProgramReminderLeadMinutes;

        return "Für {$days} ist noch kein Mittagessen eingetragen. Der Wochenüberblick geht in {$lead} Minuten an die Eltern.";
    }
}
