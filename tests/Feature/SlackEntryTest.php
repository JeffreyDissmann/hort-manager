<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_enter_sends_logged_out_users_to_the_login_screen(): void
    {
        $response = $this->get(route('slack.enter', ['to' => 'polls']));

        // No auto-Slack: show the normal login screen instead …
        $response->assertRedirect(route('login'));
        // … but remember where to land after they log in (by any method).
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

    public function test_the_hort_krank_command_reports_the_child_absent(): void
    {
        Carbon::setTestNow('2026-06-22');
        config(['services.slack.signing_secret' => 'shh']);

        $parent = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U9']);
        $child = Child::factory()->create(['name' => 'Tom']);
        $parent->children()->attach($child);

        $body = 'command='.urlencode('/hort').'&user_id=U9&text='.urlencode('krank Tom');
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'shh');

        $this->call('POST', '/slack/commands',
            ['command' => '/hort', 'user_id' => 'U9', 'text' => 'krank Tom'], [], [], [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_X-Slack-Request-Timestamp' => $timestamp,
                'HTTP_X-Slack-Signature' => $signature,
            ], $body)
            ->assertOk()
            ->assertJsonPath('response_type', 'ephemeral')
            ->assertSee('Tom')
            ->assertSee('Krank');

        $this->assertDatabaseHas('absences', [
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'reason' => 'sick',
            'reported_by' => $parent->id,
        ]);
    }

    public function test_the_krank_command_only_matches_the_callers_own_children(): void
    {
        Carbon::setTestNow('2026-06-22');
        config(['services.slack.signing_secret' => 'shh']);

        $parent = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U9']);
        $parent->children()->attach(Child::factory()->create(['name' => 'Tom']));
        $other = Child::factory()->create(['name' => 'Lena']); // not this parent's child

        $body = 'command='.urlencode('/hort').'&user_id=U9&text='.urlencode('krank Lena');
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'shh');

        $this->call('POST', '/slack/commands',
            ['command' => '/hort', 'user_id' => 'U9', 'text' => 'krank Lena'], [], [], [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_X-Slack-Request-Timestamp' => $timestamp,
                'HTTP_X-Slack-Signature' => $signature,
            ], $body)->assertOk();

        // No absence created for someone else's child.
        $this->assertDatabaseMissing('absences', ['child_id' => $other->id]);
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
