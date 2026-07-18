<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Support\WeeklyDigestBuilder;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The Monday „Wochenüberblick": the week's food + activities (Hort-wide) plus a short
 * per-child summary of what's planned for the parent's own child(ren) this week. Built
 * fresh from the notifiable at send time — the tiny payload is just the week's Monday.
 */
class WeeklyDigest extends SlackNotification
{
    public function __construct(public Carbon $weekStart) {}

    public function category(): string
    {
        return NotificationCategory::WeeklyDigest->value;
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        /** @var User $notifiable */
        $digest = WeeklyDigestBuilder::for($notifiable, $this->weekStart);

        $message = (new SlackMessage)
            ->text("Wochenüberblick {$digest['week_label']}")
            ->headerBlock("🗓️ Wochenüberblick {$digest['week_label']}");

        // Hort-wide program: one line per weekday (food · activity · homework).
        $programLines = [];
        foreach ($digest['program'] as $day) {
            $parts = array_filter([
                $day['lunch'] ? "🍽️ {$day['lunch']}" : null,
                $day['activity'] ? "✨ {$day['activity']}" : null,
                $day['homework'] ? "📚 {$day['homework']}" : null,
            ]);
            if ($parts !== []) {
                $programLines[] = "*{$day['weekday']}:* ".implode(' · ', $parts);
            }
        }

        $message->sectionBlock(function (SectionBlock $block) use ($programLines): void {
            $block->text($programLines === []
                ? '*Programm der Woche*'."\n".'_Noch nichts eingetragen._'
                : '*Programm der Woche*'."\n".implode("\n", $programLines))->markdown();
        });

        if ($digest['excursions'] !== []) {
            $trips = array_map(fn (array $e) => "*{$e['day']}:* {$e['name']}", $digest['excursions']);
            $message->sectionBlock(function (SectionBlock $block) use ($trips): void {
                $block->text('*Ausflüge*'."\n".implode("\n", $trips))->markdown();
            });
        }

        // Per-child summary.
        foreach ($digest['children'] as $child) {
            $lines = array_map(fn (array $d) => "*{$d['weekday']}:* {$d['summary']}", $child['days']);
            $message->sectionBlock(function (SectionBlock $block) use ($child, $lines): void {
                $block->text("*{$child['name']}*"."\n".implode("\n", $lines))->markdown();
            });
        }

        return $message->actionsBlock(function (ActionsBlock $block): void {
            $block->button('Wochenplan öffnen')->url(route('slack.enter', ['to' => 'weekly-plan']));
        });
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        /** @var User $notifiable */
        $digest = WeeklyDigestBuilder::for($notifiable, $this->weekStart);

        $names = array_map(fn (array $c) => $c['name'], $digest['children']);
        $body = $names === []
            ? 'Essen und Aktivitäten für diese Woche ansehen.'
            : 'Woche für '.self::joinNames($names).': Essen, Aktivitäten und Abholzeiten.';

        return (new WebPushMessage)
            ->title("Wochenüberblick {$digest['week_label']}")
            ->body($body)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => route('weekly-plan')]);
    }

    /**
     * @param  array<int, string>  $names
     */
    private static function joinNames(array $names): string
    {
        if (count($names) <= 1) {
            return $names[0] ?? '';
        }

        $last = array_pop($names);

        return implode(', ', $names).' & '.$last;
    }
}
