<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RespondToSlackCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlackCommandController extends Controller
{
    /**
     * The /hort slash command. With no argument it replies with quick links; with
     * free text ("Tom ist krank", "Lena morgen um 16:30 abholen", a question…) it
     * hands off to the assistant and posts the answer to the command's response_url.
     */
    public function handle(Request $request): JsonResponse
    {
        $text = trim((string) $request->input('text', ''));

        if ($text === '') {
            return $this->quickLinks();
        }

        RespondToSlackCommand::dispatch(
            (string) $request->input('user_id'),
            $text,
            (string) $request->input('response_url'),
        );

        return response()->json(['response_type' => 'ephemeral', 'text' => '🤔 Einen Moment …']);
    }

    /** Quick links into the app (the default /hort reply). */
    private function quickLinks(): JsonResponse
    {
        return response()->json([
            'response_type' => 'ephemeral',
            'blocks' => [
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '👋 *Hort-Manager* – tippe auf einen Bereich, oder schreib mir einfach, z. B. „Tom ist krank“ oder „Wann geht Lena heute?“:']],
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
