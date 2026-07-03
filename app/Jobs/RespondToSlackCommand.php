<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\HortAssistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Answer a /hort free-text command via the assistant, posting to its response_url. */
class RespondToSlackCommand implements ShouldQueue
{
    use Queueable;

    /** No retries: the action isn't safe to blindly repeat and a retry would double-post. */
    public int $tries = 1;

    /** Must exceed the Ollama request timeout so a slow model doesn't kill the job mid-call. */
    public int $timeout = 45;

    public function __construct(
        public string $slackUserId,
        public string $text,
        public string $responseUrl,
    ) {}

    public function handle(HortAssistant $assistant): void
    {
        if (! $this->isSlackUrl()) {
            return;
        }

        $user = User::firstWhere('slack_id', $this->slackUserId);
        $reply = $user
            ? $assistant->reply($user, $this->text)
            : 'Bitte melde dich zuerst einmal in der App an (👋 „Mit Slack anmelden“).';

        // replace_original swaps out the "🤔 Einen Moment …" ack instead of stacking a second message.
        $this->post($reply);
    }

    /** Replace the placeholder with an error note if the job blows up (e.g. Ollama timeout). */
    public function failed(Throwable $e): void
    {
        if ($this->isSlackUrl()) {
            $this->post('Das hat gerade nicht geklappt. Bitte versuch es noch einmal.');
        }
    }

    private function post(string $text): void
    {
        Http::post($this->responseUrl, [
            'response_type' => 'ephemeral',
            'replace_original' => true,
            'text' => $text,
        ]);
    }

    /** Only ever post back to Slack's own signed response URL. */
    private function isSlackUrl(): bool
    {
        return str_starts_with($this->responseUrl, 'https://hooks.slack.com/');
    }
}
