<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\HortAssistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/** Answer a direct message to the bot via the assistant, replying in the DM. */
class HandleSlackDirectMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $slackUserId,
        public string $text,
        public string $channel,
    ) {}

    public function handle(HortAssistant $assistant): void
    {
        if (! config('services.slack.notifications.bot_user_oauth_token')) {
            return;
        }

        $user = User::firstWhere('slack_id', $this->slackUserId);
        $reply = $user
            ? $assistant->reply($user, $this->text)
            : 'Bitte melde dich zuerst einmal in der App an (👋 „Mit Slack anmelden“).';

        Http::slack()->post('chat.postMessage', [
            'channel' => $this->channel,
            'text' => $reply,
        ]);
    }
}
