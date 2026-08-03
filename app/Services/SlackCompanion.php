<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Jobs\CancelCompanionSlack;
use App\Models\CompanionSlackMessage;
use App\Models\DailyDeparture;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * The Slack side of „geht mit einem anderen Kind mit": posts an interactive Ja/Nein DM
 * to the companion child's Slack-connected guardians and keeps every copy in sync —
 * once anyone answers (in Slack or the app), all guardians' messages show the result.
 * Mirrors SlackRsvp; everything is gated on the bot token (silent no-op without it).
 */
class SlackCompanion
{
    /** Ask the companion's guardians to confirm, remembering each DM for later updates. */
    public function ask(DailyDeparture $departure): void
    {
        if (! $this->configured() || ! $departure->awaitingCompanionConfirmation()) {
            return;
        }

        $departure->loadMissing('child', 'companion');
        $existing = $departure->companionSlackMessages()->get()->keyBy('user_id');

        $guardians = $departure->companion->guardians()->onSlack()->get()
            ->filter(fn (User $guardian) => $guardian->wantsNotification(NotificationCategory::Companion->value, 'slack'));

        foreach ($guardians as $guardian) {
            // Re-asking (e.g. after a reopen) updates the same DM back to buttons rather
            // than posting a duplicate.
            $message = $existing->get($guardian->id);
            if ($message) {
                $this->call('chat.update', [
                    'channel' => $message->channel,
                    'ts' => $message->ts,
                    'text' => $this->fallback($departure),
                    'blocks' => $this->askBlocks($departure),
                ]);

                continue;
            }

            $response = $this->call('chat.postMessage', [
                'channel' => $guardian->slack_id,
                'text' => $this->fallback($departure),
                'blocks' => $this->askBlocks($departure),
            ]);

            if ($response['ok'] ?? false) {
                CompanionSlackMessage::create([
                    'daily_departure_id' => $departure->id,
                    'user_id' => $guardian->id,
                    'channel' => $response['channel'],
                    'ts' => $response['ts'],
                ]);
            }
        }
    }

    /** After an answer, re-render every guardian's DM to show the recorded result. */
    public function sync(DailyDeparture $departure): void
    {
        if (! $this->configured()) {
            return;
        }

        $departure->loadMissing('child', 'companion', 'companionConfirmedBy');

        foreach ($departure->companionSlackMessages()->get() as $message) {
            $this->call('chat.update', [
                'channel' => $message->channel,
                'ts' => $message->ts,
                'text' => $this->fallback($departure),
                'blocks' => $this->answeredBlocks($departure),
            ]);
        }
    }

    /**
     * Queue a cancellation for a departure that's about to be unwound: capture its DM
     * coordinates now (they'd be gone once the row — and its messages — are deleted) and
     * hand them to the job. Call this *before* deleting the row.
     */
    public static function cancelFor(DailyDeparture $departure, string $child, string $companion): void
    {
        $messages = $departure->companionSlackMessages()
            ->get(['channel', 'ts'])
            ->map(fn (CompanionSlackMessage $m) => ['channel' => $m->channel, 'ts' => $m->ts])
            ->all();

        if ($messages !== []) {
            CancelCompanionSlack::dispatch($messages, $child, $companion);
        }
    }

    /**
     * Replace each remembered DM with a short note. Given plain message coordinates
     * (via CancelCompanionSlack) so it survives the departure row being deleted.
     *
     * @param  array<int, array{channel: string, ts: string}>  $messages
     */
    public function cancel(array $messages, string $child, string $companion): void
    {
        if (! $this->configured()) {
            return;
        }

        foreach ($messages as $message) {
            $this->call('chat.update', [
                'channel' => $message['channel'],
                'ts' => $message['ts'],
                'text' => "Hat sich erledigt: {$child} geht doch nicht mit {$companion} mit.",
                'blocks' => [$this->section("🚫 *Hat sich erledigt.*\n{$child} geht doch nicht mit *{$companion}* mit nach Hause.")],
            ]);
        }
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

    private function fallback(DailyDeparture $departure): string
    {
        return "{$departure->child->name} möchte mit {$departure->companion->name} mit nach Hause.";
    }

    /**
     * Unanswered DM: the request + Ja/Nein buttons.
     *
     * @return array<int, array<string, mixed>>
     */
    private function askBlocks(DailyDeparture $departure): array
    {
        $date = $departure->date->format('d.m.Y');
        $lines = ['🏠 *Mitgehen*', "*{$departure->child->name}* möchte am {$date} mit *{$departure->companion->name}* mit nach Hause."];
        if ($departure->note) {
            $lines[] = "📝 {$departure->note}";
        }

        return [
            $this->section(implode("\n", $lines)),
            [
                'type' => 'actions',
                'elements' => [
                    $this->button('✅ Ja, in Ordnung', 'primary', "companion_{$departure->id}_yes", "companion|{$departure->id}|1"),
                    $this->button('❌ Nein', 'danger', "companion_{$departure->id}_no", "companion|{$departure->id}|0"),
                ],
            ],
        ];
    }

    /**
     * Answered DM: the recorded result (and who gave it) + an „Ändern" link to the app.
     *
     * @return array<int, array<string, mixed>>
     */
    private function answeredBlocks(DailyDeparture $departure): array
    {
        $child = $departure->child->name;
        $companion = $departure->companion->name;
        $by = $departure->companionConfirmedBy?->name;

        $status = $departure->companion_confirmed
            ? "✅ *{$child}* geht mit *{$companion}* mit nach Hause."
            : "❌ *{$child}* geht nicht mit *{$companion}* mit.";

        return [$this->section(
            $status.($by ? " _(von {$by})_" : ''),
            $this->linkButton('Ändern', route('slack.enter', ['to' => 'weekly-plan'])),
        )];
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
    private function linkButton(string $text, string $url): array
    {
        return [
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $text, 'emoji' => true],
            'url' => $url,
        ];
    }
}
