<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Channels\SelfHealingSlackChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SlackNotificationRouterChannel;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RuntimeException;
use Tests\TestCase;

/**
 * A Slack id Slack no longer knows would otherwise fail every future DM to that user
 * — the departure notice, the reminders, the digest — straight into failed_jobs.
 */
class SelfHealingSlackChannelTest extends TestCase
{
    use RefreshDatabase;

    private function channel(string $error): SelfHealingSlackChannel
    {
        $inner = new class(app()) extends SlackNotificationRouterChannel
        {
            public static string $error = '';

            public function send($notifiable, Notification $notification)
            {
                throw new RuntimeException(self::$error);
            }
        };

        $inner::$error = $error;

        return new SelfHealingSlackChannel($inner);
    }

    public function test_a_stale_id_is_cleared_and_the_send_is_swallowed(): void
    {
        $user = User::factory()->create(['slack_id' => 'U-GONE']);

        $this->channel('Slack API call failed with error [channel_not_found].')
            ->send($user, new class extends Notification {});

        $this->assertNull($user->fresh()->slack_id);
    }

    public function test_any_other_slack_error_still_fails_loudly(): void
    {
        $user = User::factory()->create(['slack_id' => 'U-FINE']);

        $this->expectException(RuntimeException::class);

        try {
            $this->channel('Slack API call failed with error [invalid_auth].')
                ->send($user, new class extends Notification {});
        } finally {
            // A bad token is our problem, not the user's — keep their id.
            $this->assertSame('U-FINE', $user->fresh()->slack_id);
        }
    }

    public function test_a_user_whose_id_vanished_before_the_job_ran_is_skipped(): void
    {
        // Notifications queue per channel: the id can be cleared (by this very healing)
        // between queueing the Slack job and running it, leaving nothing to route to.
        $user = User::factory()->create(['slack_id' => null]);

        $this->channel('should never be reached')
            ->send($user, new class extends Notification {});

        $this->assertNull($user->fresh()->slack_id);
    }

    public function test_the_channel_is_registered_for_slack(): void
    {
        $this->assertInstanceOf(
            SelfHealingSlackChannel::class,
            NotificationFacade::channel('slack'),
        );
    }
}
