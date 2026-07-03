<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\HortAssistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/** Answer a /hort free-text command via the assistant, posting to its response_url. */
class RespondToSlackCommand implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $slackUserId,
        public string $text,
        public string $responseUrl,
    ) {}

    public function handle(HortAssistant $assistant): void
    {
        // Only ever post back to Slack's own signed response URL.
        if (! str_starts_with($this->responseUrl, 'https://hooks.slack.com/')) {
            return;
        }

        $user = User::firstWhere('slack_id', $this->slackUserId);
        $reply = $user
            ? $assistant->reply($user, $this->text)
            : 'Bitte melde dich zuerst einmal in der App an (👋 „Mit Slack anmelden“).';

        Http::post($this->responseUrl, ['response_type' => 'ephemeral', 'text' => $reply]);
    }
}
