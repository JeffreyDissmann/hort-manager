<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\HortIntentAgent;
use App\Jobs\RespondToSlackCommand;
use App\Models\Child;
use App\Models\User;
use App\Services\HortAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SlackEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_enter_redirects_authenticated_users_straight_to_the_target(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('slack.enter', ['to' => 'polls']))
            ->assertRedirect(route('polls.index'));
    }

    public function test_enter_starts_slack_sign_in_when_logged_out(): void
    {
        config([
            'services.slack.client_id' => 'id',
            'services.slack.client_secret' => 'secret',
            'services.slack.redirect' => 'http://localhost/auth/slack/callback',
        ]);

        $response = $this->get(route('slack.enter', ['to' => 'polls']));

        $response->assertRedirect();
        $this->assertStringContainsString('slack.com', $response->headers->get('Location'));
        // Remembers where to land after the Slack round-trip.
        $this->assertSame(route('polls.index'), session('url.intended'));
    }

    public function test_an_unknown_target_falls_back_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('slack.enter', ['to' => 'nonsense']))
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_hort_command_replies_with_app_links(): void
    {
        config(['services.slack.signing_secret' => 'shh']);
        $body = 'command='.urlencode('/hort').'&user_id=U1';
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'shh');

        $this->call('POST', '/slack/commands', ['command' => '/hort', 'user_id' => 'U1'], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X-Slack-Request-Timestamp' => $timestamp,
            'HTTP_X-Slack-Signature' => $signature,
        ], $body)
            ->assertOk()
            ->assertJsonPath('response_type', 'ephemeral')
            ->assertJsonFragment(['url' => route('slack.enter', ['to' => 'polls'])]);
    }

    public function test_hort_with_free_text_hands_off_to_the_assistant(): void
    {
        Queue::fake();
        config(['services.slack.signing_secret' => 'shh']);

        $url = 'https://hooks.slack.com/commands/T/123';
        $body = 'command='.urlencode('/hort').'&user_id=U9&text='.urlencode('Tom ist krank').'&response_url='.urlencode($url);
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'shh');

        $this->call('POST', '/slack/commands',
            ['command' => '/hort', 'user_id' => 'U9', 'text' => 'Tom ist krank', 'response_url' => $url], [], [], [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_X-Slack-Request-Timestamp' => $timestamp,
                'HTTP_X-Slack-Signature' => $signature,
            ], $body)
            ->assertOk()
            ->assertJsonPath('response_type', 'ephemeral');

        Queue::assertPushed(RespondToSlackCommand::class, fn ($job) => $job->slackUserId === 'U9'
            && $job->text === 'Tom ist krank'
            && $job->responseUrl === $url);
    }

    public function test_the_command_job_replaces_the_placeholder_with_the_answer(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
        $user = User::factory()->create(['slack_id' => 'U9']);
        $child = Child::factory()->create(['name' => 'Tom']);
        $user->children()->attach($child);
        HortIntentAgent::fake(fn () => [
            'intent' => 'krank', 'kind' => 'Tom', 'datum' => 'heute',
            'uhrzeit' => null, 'art' => null, 'ausflug' => null, 'zusage' => null,
        ]);

        (new RespondToSlackCommand('U9', 'Tom ist krank', 'https://hooks.slack.com/commands/T/1'))
            ->handle(app(HortAssistant::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/commands/T/1'
            && $request['replace_original'] === true
            && str_contains((string) $request['text'], 'Tom'));
    }

    public function test_the_command_rejects_a_bad_signature(): void
    {
        config(['services.slack.signing_secret' => 'shh']);
        $body = 'command='.urlencode('/hort');
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'wrong-secret');

        $this->call('POST', '/slack/commands', ['command' => '/hort'], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X-Slack-Request-Timestamp' => $timestamp,
            'HTTP_X-Slack-Signature' => $signature,
        ], $body)->assertForbidden();
    }
}
