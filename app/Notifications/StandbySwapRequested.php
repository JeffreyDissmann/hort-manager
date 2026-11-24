<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\StandbySwap;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Asks the guardians of the wanted date's child whether they'll swap: they would take
 * the offered date and hand over theirs. Answered on the Springerdienst page.
 */
class StandbySwapRequested extends SlackNotification
{
    public function __construct(public StandbySwap $swap) {}

    public function category(): string
    {
        return NotificationCategory::Standby->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $body = $this->body();

        return (new SlackMessage)
            ->text($body)
            ->sectionBlock(fn (SectionBlock $block) => $block->text($body)->markdown())
            ->actionsBlock(fn (ActionsBlock $block) => $block
                ->button('Im Hort-Manager beantworten')
                ->url(route('slack.enter', ['to' => 'standby'])));
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Springerdienst: Tauschanfrage')
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('standby.index')]);
    }

    private function body(): string
    {
        $swap = $this->swap->loadMissing(['offeredDuty', 'wantedDuty', 'offeredChild', 'wantedChild']);

        return sprintf(
            'Die Familie von %s bittet um einen Springerdienst-Tausch: Ihr übernehmt den %s, die Familie von %s dafür euren %s (bisher %s). Getauscht wird erst, wenn ihr zustimmt.',
            $swap->offeredChild->name,
            self::day($swap->offeredDuty->date),
            $swap->offeredChild->name,
            self::day($swap->wantedDuty->date),
            $swap->wantedChild->name,
        );
    }

    /** „Di 3.3." — short enough for a push line. */
    public static function day(Carbon $date): string
    {
        return $date->copy()->locale('de')->isoFormat('dd D.M.');
    }
}
