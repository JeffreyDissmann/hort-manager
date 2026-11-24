<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\StandbySwapStatus;
use App\Models\StandbySwap;
use App\Models\StandbySwapSlackMessage;
use App\Models\User;
use App\Notifications\StandbySwapRequested;
use Illuminate\Support\Facades\Http;

/**
 * The Slack side of a Springerdienst swap request: posts an Annehmen/Ablehnen DM to
 * the guardians of the requested date's child and keeps every copy in sync — once
 * anyone answers (in Slack or the app), or the request is withdrawn or lapses, all
 * copies show the outcome instead of the buttons. Mirrors SlackCompanion; a silent
 * no-op without the bot token.
 */
class SlackStandbySwap
{
    /** Ask the other family, remembering each DM for later updates. */
    public function ask(StandbySwap $swap): void
    {
        if (! $this->configured() || $swap->status !== StandbySwapStatus::Pending) {
            return;
        }

        $swap->loadMissing(['offeredDuty', 'wantedDuty', 'offeredChild', 'wantedChild']);

        $guardians = $swap->wantedChild->guardians()->onSlack()->get()
            ->filter(fn (User $guardian) => $guardian->wantsNotification(NotificationCategory::Standby->value, 'slack'));

        foreach ($guardians as $guardian) {
            $response = $this->call('chat.postMessage', [
                'channel' => $guardian->slack_id,
                'text' => StandbySwapRequested::describe($swap),
                'blocks' => $this->askBlocks($swap),
            ]);

            if ($response['ok'] ?? false) {
                StandbySwapSlackMessage::create([
                    'standby_swap_id' => $swap->id,
                    'user_id' => $guardian->id,
                    'channel' => $response['channel'],
                    'ts' => $response['ts'],
                ]);
            }
        }
    }

    /** Replace the buttons in every remembered DM with the request's outcome. */
    public function sync(StandbySwap $swap): void
    {
        if (! $this->configured() || $swap->status === StandbySwapStatus::Pending) {
            return;
        }

        $swap->loadMissing(['offeredDuty', 'wantedDuty', 'offeredChild', 'wantedChild', 'answerer']);
        $text = $this->outcome($swap);

        foreach ($swap->slackMessages()->get() as $message) {
            $this->call('chat.update', [
                'channel' => $message->channel,
                'ts' => $message->ts,
                'text' => str_replace(['*', '_'], '', $text),
                'blocks' => [$this->section($text, $this->linkButton('Springerdienst ansehen'))],
            ]);
        }
    }

    /** What happened, from the point of view of the family that was asked. */
    private function outcome(StandbySwap $swap): string
    {
        $offered = StandbySwapRequested::day($swap->offeredDuty->date);
        $wanted = StandbySwapRequested::day($swap->wantedDuty->date);
        $by = $swap->answerer ? " _(von {$swap->answerer->name})_" : '';

        return match ($swap->status) {
            StandbySwapStatus::Accepted => "✅ *Getauscht.* Ihr seid jetzt am {$offered} eingeteilt statt am {$wanted}.{$by}",
            StandbySwapStatus::Declined => "❌ *Abgelehnt.* Ihr bleibt am {$wanted} eingeteilt.{$by}",
            StandbySwapStatus::Cancelled => "🚫 *Hat sich erledigt:* Die Familie von {$swap->offeredChild->name} hat die Anfrage zurückgezogen. Ihr bleibt am {$wanted} eingeteilt.",
            default => '🚫 *Hat sich erledigt:* Einer der beiden Tage wurde inzwischen anders vergeben. Getauscht wurde nichts.',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function askBlocks(StandbySwap $swap): array
    {
        return [
            $this->section("🔄 *Springerdienst-Tausch*\n".StandbySwapRequested::describe($swap)),
            [
                'type' => 'actions',
                'elements' => [
                    $this->button('✅ Annehmen', 'primary', "standby_swap_{$swap->id}_yes", "standby_swap|{$swap->id}|1"),
                    $this->button('❌ Ablehnen', 'danger', "standby_swap_{$swap->id}_no", "standby_swap|{$swap->id}|0"),
                    $this->linkButton('Im Hort-Manager ansehen'),
                ],
            ],
        ];
    }

    private function configured(): bool
    {
        return (bool) config('services.slack.notifications.bot_user_oauth_token');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        return Http::slack()->post($method, $payload)->json() ?? [];
    }

    /**
     * @param  array<string, mixed>|null  $accessory
     * @return array<string, mixed>
     */
    private function section(string $text, ?array $accessory = null): array
    {
        $block = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text]];

        if ($accessory) {
            $block['accessory'] = $accessory;
        }

        return $block;
    }

    /** @return array<string, mixed> */
    private function button(string $text, string $style, string $actionId, string $value): array
    {
        return [
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $text, 'emoji' => true],
            'style' => $style,
            'action_id' => $actionId,
            'value' => $value,
        ];
    }

    /** @return array<string, mixed> */
    private function linkButton(string $text): array
    {
        return [
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $text, 'emoji' => true],
            'url' => route('slack.enter', ['to' => 'standby']),
        ];
    }
}
