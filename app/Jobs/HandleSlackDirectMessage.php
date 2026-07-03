<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\HortAssistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Answer a direct message to the bot via the assistant, replying in the DM. */
class HandleSlackDirectMessage implements ShouldQueue
{
    use Queueable;

    /** No retries: the action isn't safe to blindly repeat and a retry would double-post. */
    public int $tries = 1;

    /** Must exceed the Ollama request timeout so a slow model doesn't kill the job mid-call. */
    public int $timeout = 45;

    public function __construct(
        public string $slackUserId,
        public string $text,
        public string $channel,
    ) {}

    public function handle(HortAssistant $assistant): void
    {
        if (! $this->enabled()) {
            return;
        }

        // Immediate feedback — the model can take several seconds — then swap this
        // placeholder for the real answer in place (chat.update on the same ts).
        $ts = Http::slack()->post('chat.postMessage', [
            'channel' => $this->channel,
            'text' => '🤔 Einen Moment …',
        ])->json('ts');

        $user = User::firstWhere('slack_id', $this->slackUserId);
        $reply = $user
            ? $assistant->reply($user, $this->text)
            : 'Bitte melde dich zuerst einmal in der App an (👋 „Mit Slack anmelden“).';

        if ($ts) {
            Http::slack()->post('chat.update', [
                'channel' => $this->channel,
                'ts' => $ts,
                'text' => $reply,
            ]);
        } else {
            Http::slack()->post('chat.postMessage', ['channel' => $this->channel, 'text' => $reply]);
        }
    }

    /** Let the parent know their DM fell through rather than leaving them hanging. */
    public function failed(Throwable $e): void
    {
        if ($this->enabled()) {
            Http::slack()->post('chat.postMessage', [
                'channel' => $this->channel,
                'text' => 'Das hat gerade nicht geklappt. Bitte versuch es noch einmal.',
            ]);
        }
    }

    private function enabled(): bool
    {
        return (bool) config('services.slack.notifications.bot_user_oauth_token');
    }
}
