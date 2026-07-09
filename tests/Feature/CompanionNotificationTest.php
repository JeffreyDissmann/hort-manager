<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;
use App\Notifications\CompanionAnswered;
use App\Notifications\CompanionCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

/**
 * The requester-facing companion notifications are dual-channel: Slack (when the bot
 * token is set) plus web-push (when the recipient opted in). These lock the `via()`
 * channel set so a regression can't silently drop a delivery route.
 */
class CompanionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function departure(): DailyDeparture
    {
        $tom = Child::factory()->create(['name' => 'Tom']);
        $emma = Child::factory()->create(['name' => 'Emma']);

        return DailyDeparture::create([
            'child_id' => $tom->id,
            'date' => '2026-06-24',
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $emma->id,
            'companion_confirmed' => true,
            'status' => DepartureStatus::Present,
        ]);
    }

    public function test_companion_answered_goes_to_both_slack_and_web_push(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $guardian->updatePushSubscription('https://push.example/g', 'k', 'a');

        $channels = (new CompanionAnswered($this->departure(), true))->via($guardian);

        $this->assertContains('slack', $channels);
        $this->assertContains(WebPushChannel::class, $channels);
    }

    public function test_companion_cancelled_goes_to_both_slack_and_web_push(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $guardian->updatePushSubscription('https://push.example/g', 'k', 'a');

        $channels = (new CompanionCancelled('Tom', 'Emma', '24.06.'))->via($guardian);

        $this->assertContains('slack', $channels);
        $this->assertContains(WebPushChannel::class, $channels);
    }

    public function test_without_a_slack_token_only_web_push_remains(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => null]);
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $guardian->updatePushSubscription('https://push.example/g', 'k', 'a');

        $channels = (new CompanionAnswered($this->departure(), false))->via($guardian);

        $this->assertSame([WebPushChannel::class], $channels);
    }

    public function test_without_a_push_subscription_only_slack_remains(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);

        $channels = (new CompanionAnswered($this->departure(), true))->via($guardian);

        $this->assertSame(['slack'], $channels);
    }
}
