<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;
use App\Notifications\ChildDeparted;
use App\Notifications\NewExcursion;
use App\Services\SlackCompanion;
use App\Services\SlackRsvp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

/**
 * The per-user opt-out preferences gate both notification paths: the Notification::via()
 * classes and the direct Slack services. Default (no prefs) = every channel on.
 */
class NotificationPreferenceGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
    }

    private function departure(): DailyDeparture
    {
        $child = Child::factory()->create();

        return DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-24',
            'status' => DepartureStatus::Present,
        ]);
    }

    public function test_defaults_to_both_channels_when_no_prefs_are_set(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $user->updatePushSubscription('https://push.example/a', 'k', 'a');

        $channels = (new ChildDeparted($this->departure()))->via($user);

        $this->assertContains('slack', $channels);
        $this->assertContains(WebPushChannel::class, $channels);
    }

    public function test_slack_off_drops_only_the_slack_channel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $user->updatePushSubscription('https://push.example/a', 'k', 'a');
        $user->notification_preferences = ['departures' => ['slack' => false, 'push' => true]];
        $user->save();

        $channels = (new ChildDeparted($this->departure()))->via($user);

        $this->assertNotContains('slack', $channels);
        $this->assertContains(WebPushChannel::class, $channels);
    }

    public function test_push_off_drops_the_push_channel_on_a_push_only_notification(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent]);
        $user->updatePushSubscription('https://push.example/a', 'k', 'a');
        $user->notification_preferences = ['excursions' => ['slack' => true, 'push' => false]];
        $user->save();

        $excursion = Excursion::factory()->create();

        $this->assertSame([], (new NewExcursion($excursion))->via($user));
    }

    public function test_slack_rsvp_service_skips_a_guardian_with_excursion_slack_off(): void
    {
        Http::fake(['slack.com/api/*' => Http::response(['ok' => true, 'channel' => 'D1', 'ts' => 'ts1'])]);

        $child = Child::factory()->create();
        $optedOut = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U-out']);
        $optedOut->notification_preferences = ['excursions' => ['slack' => false, 'push' => true]];
        $optedOut->save();
        $child->guardians()->attach($optedOut);

        $excursion = Excursion::factory()->create();
        $excursion->children()->attach($child->id, ['response' => null]);

        app(SlackRsvp::class)->announce($excursion);

        Http::assertNothingSent();
    }

    public function test_slack_companion_service_skips_a_guardian_with_companion_slack_off(): void
    {
        Http::fake(['slack.com/api/*' => Http::response(['ok' => true, 'channel' => 'D1', 'ts' => 'ts1'])]);

        $tom = Child::factory()->create(['name' => 'Tom']);
        $emma = Child::factory()->create(['name' => 'Emma']);
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U-emma']);
        $guardian->notification_preferences = ['companion' => ['slack' => false, 'push' => true]];
        $guardian->save();
        $emma->guardians()->attach($guardian);

        $departure = DailyDeparture::create([
            'child_id' => $tom->id,
            'date' => '2026-06-24',
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $emma->id,
            'companion_confirmed' => null,
            'status' => DepartureStatus::Present,
        ]);

        app(SlackCompanion::class)->ask($departure);

        Http::assertNothingSent();
    }
}
