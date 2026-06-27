<?php

namespace App\Services;

use App\Models\Child;
use App\Models\Excursion;
use App\Models\ExcursionSlackMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Posts the per-child RSVP DM to each guardian and keeps every copy in sync:
 * once anyone answers (in Slack or the app), the child's row loses its buttons
 * and shows the recorded result on all guardians' messages.
 */
class SlackRsvp
{
    /** Post the RSVP DM to every Slack-connected guardian and remember each message. */
    public function announce(Excursion $excursion): void
    {
        if (! $this->configured()) {
            return;
        }

        foreach (User::guardians()->onSlack()->get() as $guardian) {
            $response = $this->call('chat.postMessage', [
                'channel' => $guardian->slack_id,
                'text' => $this->fallback($excursion),
                'blocks' => $this->blocks($excursion, $guardian),
            ]);

            if ($response['ok'] ?? false) {
                ExcursionSlackMessage::updateOrCreate(
                    ['excursion_id' => $excursion->id, 'user_id' => $guardian->id],
                    ['channel' => $response['channel'], 'ts' => $response['ts']],
                );
            }
        }
    }

    /** After an answer, re-render every guardian's DM for the child's excursion. */
    public function syncForChild(Excursion $excursion, Child $child): void
    {
        if (! $this->configured()) {
            return;
        }

        $messages = ExcursionSlackMessage::query()
            ->where('excursion_id', $excursion->id)
            ->whereIn('user_id', $child->guardians()->pluck('users.id'))
            ->with('user')
            ->get();

        foreach ($messages as $message) {
            $this->call('chat.update', [
                'channel' => $message->channel,
                'ts' => $message->ts,
                'text' => $this->fallback($excursion),
                'blocks' => $this->blocks($excursion, $message->user),
            ]);
        }
    }

    /** The excursion was deleted — replace every DM with a cancellation note. */
    public function cancel(Excursion $excursion): void
    {
        if (! $this->configured()) {
            return;
        }

        foreach (ExcursionSlackMessage::where('excursion_id', $excursion->id)->get() as $message) {
            $this->call('chat.update', [
                'channel' => $message->channel,
                'ts' => $message->ts,
                'text' => "Ausflug abgesagt: {$excursion->name}.",
                'blocks' => [$this->section("🚫 *Ausflug abgesagt:* {$excursion->name}\nDieser Ausflug findet nicht statt.")],
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
        return Http::withToken(config('services.slack.notifications.bot_user_oauth_token'))
            ->post("https://slack.com/api/{$method}", $payload)
            ->json() ?? [];
    }

    private function fallback(Excursion $excursion): string
    {
        return "Ausflug: {$excursion->name} am ".$excursion->date->format('d.m.Y').'.';
    }

    /**
     * Block Kit for one guardian: trip details, then a row per child of theirs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function blocks(Excursion $excursion, User $guardian): array
    {
        $blocks = [$this->detailsBlock($excursion)];

        $pivots = $excursion->children()
            ->whereIn('children.id', $guardian->children()->pluck('children.id'))
            ->get()
            ->keyBy('id');

        foreach ($guardian->children as $child) {
            $blocks = array_merge($blocks, $this->childBlocks($excursion, $child, $pivots->get($child->id)?->pivot));
        }

        return $blocks;
    }

    /**
     * Unanswered → Ja/Nein buttons; answered → the recorded result (and who gave it).
     *
     * @return array<int, array<string, mixed>>
     */
    private function childBlocks(Excursion $excursion, Child $child, mixed $pivot): array
    {
        if ($pivot === null || $pivot->response === null) {
            return [
                $this->section("Kommt *{$child->name}* mit?"),
                [
                    'type' => 'actions',
                    'elements' => [
                        $this->button('✅ Ja', 'primary', "rsvp_{$child->id}_yes", "rsvp|{$excursion->id}|{$child->id}|1"),
                        $this->button('❌ Nein', 'danger', "rsvp_{$child->id}_no", "rsvp|{$excursion->id}|{$child->id}|0"),
                    ],
                ],
            ];
        }

        $by = $pivot->answered_by ? User::find($pivot->answered_by)?->name : null;
        $status = (bool) (int) $pivot->response
            ? "✅ *{$child->name}* kommt mit."
            : "❌ *{$child->name}* kommt nicht mit.";

        // Answered — changing it only works in the app, so offer a link there
        // (slack.enter signs the user in via Slack first if needed).
        return [$this->section(
            $status.($by ? " _(von {$by})_" : ''),
            $this->linkButton('Ändern', route('slack.enter', ['to' => 'polls'])),
        )];
    }

    /** @return array<string, mixed> */
    private function detailsBlock(Excursion $excursion): array
    {
        $depart = $excursion->depart_at ? substr((string) $excursion->depart_at, 0, 5) : null;
        $return = $excursion->return_at ? substr((string) $excursion->return_at, 0, 5) : null;

        $lines = ["🚌 *{$excursion->name}*", '📅 '.$excursion->date->format('d.m.Y')];
        if ($depart) {
            $lines[] = '🕐 '.$depart.($return ? "–{$return}" : '').' Uhr';
        }
        if ($excursion->note) {
            $lines[] = "📝 {$excursion->note}";
        }
        if ($excursion->rsvp_deadline) {
            $lines[] = '⏰ Rückmeldung bis *'.$excursion->rsvp_deadline->format('d.m.Y').'*';
        }

        return $this->section(implode("\n", $lines));
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

    /** A link button (opens the app — no interaction callback). */
    private function linkButton(string $text, string $url): array
    {
        return [
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $text, 'emoji' => true],
            'url' => $url,
        ];
    }
}
