<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_events_require_a_valid_signature(): void
    {
        $this->postEvent(['type' => 'url_verification', 'challenge' => 'abc123'], validSignature: false)
            ->assertForbidden();
    }
}
