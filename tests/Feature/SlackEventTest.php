<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\HortIntentAgent;
use App\Jobs\HandleSlackDirectMessage;
use App\Models\Child;
use App\Models\User;
use App\Services\HortAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SlackEventTest extends TestCase
{
    use RefreshDatabase;

    private function postEvent(array $payload, bool $validSignature = true): TestResponse
    {
        config(['services.slack.signing_secret' => 'shh']);

        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", $validSignature ? 'shh' : 'wrong-secret');

        return $this->call('POST', '/slack/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Slack-Request-Timestamp' => $timestamp,
            'HTTP_X-Slack-Signature' => $signature,
        ], $body);
    }

    public function test_it_answers_the_url_verification_challenge(): void
    {
        $this->postEvent(['type' => 'url_verification', 'challenge' => 'abc123'])
            ->assertOk()
            ->assertSee('abc123');
    }

    public function test_opening_the_home_tab_publishes_a_view(): void
    {
        Http::fake(['slack.com/api/views.publish' => Http::response(['ok' => true])]);
        User::factory()->create(['slack_id' => 'U1', 'name' => 'Mama Muster']);

        $this->postEvent([
            'type' => 'event_callback',
            'event' => ['type' => 'app_home_opened', 'user' => 'U1'],
        ])->assertNoContent();

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/views.publish'
            && $request['user_id'] === 'U1'
            && data_get($request->data(), 'view.type') === 'home');
    }

    public function test_a_direct_message_is_handed_to_the_assistant(): void
    {
        Queue::fake();

        $this->postEvent([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'channel_type' => 'im',
                'user' => 'U1',
                'channel' => 'D1',
                'text' => 'Tom ist krank',
            ],
        ])->assertNoContent();

        Queue::assertPushed(HandleSlackDirectMessage::class, fn ($job) => $job->slackUserId === 'U1'
            && $job->text === 'Tom ist krank'
            && $job->channel === 'D1');
    }

    public function test_the_bots_own_dm_messages_are_ignored(): void
    {
        Queue::fake();

        $this->postEvent([
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'channel_type' => 'im',
                'bot_id' => 'B1', // the bot's own reply — must not loop
                'channel' => 'D1',
                'text' => 'Tom ist krank',
            ],
        ])->assertNoContent();

        Queue::assertNothingPushed();
    }

    public function test_events_require_a_valid_signature(): void
    {
        $this->postEvent(['type' => 'url_verification', 'challenge' => 'abc123'], validSignature: false)
            ->assertForbidden();
    }

    public function test_the_dm_job_posts_an_ack_then_updates_it_with_the_reply(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '111.222']),
            'slack.com/api/chat.update' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create(['slack_id' => 'U1']);
        $child = Child::factory()->create(['name' => 'Tom']);
        $user->children()->attach($child);
        HortIntentAgent::fake(fn () => [
            'intent' => 'krank', 'kind' => 'Tom', 'datum' => 'heute',
            'uhrzeit' => null, 'art' => null, 'ausflug' => null, 'zusage' => null,
        ]);

        (new HandleSlackDirectMessage('U1', 'Tom ist krank', 'D1'))->handle(app(HortAssistant::class));

        // Immediate placeholder …
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'D1'
            && str_contains((string) $request['text'], 'Moment'));
        // … then the real answer swapped in on the same message.
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
            && $request['channel'] === 'D1'
            && $request['ts'] === '111.222'
            && str_contains((string) $request['text'], 'Tom'));
    }

    public function test_the_dm_job_asks_unknown_users_to_sign_in(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '111.222']),
            'slack.com/api/chat.update' => Http::response(['ok' => true]),
        ]);

        (new HandleSlackDirectMessage('U-nobody', 'Hallo', 'D1'))->handle(app(HortAssistant::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
            && str_contains((string) $request['text'], 'anmelden'));
    }

    public function test_the_dm_job_stays_silent_without_a_bot_token(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => null]);
        Http::preventStrayRequests();

        (new HandleSlackDirectMessage('U1', 'Tom ist krank', 'D1'))->handle(app(HortAssistant::class));

        Http::assertNothingSent();
    }
}
