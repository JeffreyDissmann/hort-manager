<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/** Publishes the bot's App Home tab — a welcome with quick links into the app. */
class SlackHome
{
    public function publish(string $slackUserId): void
    {
        if (! config('services.slack.notifications.bot_user_oauth_token')) {
            return;
        }

        $user = User::firstWhere('slack_id', $slackUserId);
        $greeting = $user ? "Hallo {$user->name}! 👋" : 'Willkommen beim Hort-Manager! 👋';

        Http::slack()->post('views.publish', [
            'user_id' => $slackUserId,
            'view' => $this->view($greeting),
        ]);
    }

    /** @return array<string, mixed> */
    private function view(string $greeting): array
    {
        return [
            'type' => 'home',
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => '🏠 Hort-Manager', 'emoji' => true]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $greeting]],
                ['type' => 'divider'],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*Schnellzugriff*']],
                [
                    'type' => 'actions',
                    'elements' => [
                        $this->link('🏠 Zur App', 'board'),
                        $this->link('🚌 Ausflüge', 'polls'),
                        $this->link('👧 Kinder', 'children'),
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function link(string $text, string $to): array
    {
        return [
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $text, 'emoji' => true],
            'url' => route('slack.enter', ['to' => $to]),
        ];
    }
}
