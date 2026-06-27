<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SlackCommandController extends Controller
{
    /** The /hort slash command — replies (only to the caller) with quick links into the app. */
    public function handle(): JsonResponse
    {
        return response()->json([
            'response_type' => 'ephemeral',
            'blocks' => [
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '👋 *Hort-Manager* – tippe auf einen Bereich:']],
                [
                    'type' => 'actions',
                    'elements' => [
                        $this->link('🏠 Heute', 'board'),
                        $this->link('🚌 Ausflüge', 'polls'),
                        $this->link('👧 Kinder', 'children'),
                    ],
                ],
            ],
        ]);
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
