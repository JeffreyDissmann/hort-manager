<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\HandleSlackDirectMessage;
use App\Jobs\PublishSlackHome;
use App\Support\AssistantRateLimit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SlackEventController extends Controller
{
    /**
     * Slack Events API endpoint (signature-verified). Answers the URL verification
     * challenge, (re)publishes the App Home tab, and hands direct messages to the
     * assistant off-request via a queued job.
     */
    public function handle(Request $request): Response
    {
        if ($request->input('type') === 'url_verification') {
            return response($request->input('challenge'));
        }

        // Slack re-delivers an event if it doesn't see a prompt 200 (its own
        // hiccup, a slow hop). We already ack immediately and process async, so a
        // retry would only double-handle the same message — ignore retries.
        if ($request->hasHeader('X-Slack-Retry-Num')) {
            return response()->noContent();
        }

        $event = (array) $request->input('event', []);
        $type = $event['type'] ?? null;

        if ($type === 'app_home_opened') {
            PublishSlackHome::dispatch($event['user']);
        }

        // A real user's DM to the bot — not its own posts, edits or joins.
        if ($type === 'message'
            && ($event['channel_type'] ?? null) === 'im'
            && empty($event['bot_id'])
            && empty($event['subtype'])
            && ! empty($event['user'])
            // Over the per-user assistant limit (shared with /hort) → drop the excess DM.
            && AssistantRateLimit::attempt((string) $event['user'])) {
            HandleSlackDirectMessage::dispatch(
                $event['user'],
                (string) ($event['text'] ?? ''),
                $event['channel'],
            );
        }

        return response()->noContent();
    }
}
