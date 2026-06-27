<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
