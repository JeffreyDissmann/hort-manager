<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;
use App\Notifications\ChildDeparted;
use App\Notifications\NewExcursion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_and_remove_a_push_subscription(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), [
                'endpoint' => 'https://push.example/abc',
                'keys' => ['p256dh' => 'PKEY', 'auth' => 'AUTH'],
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example/abc']);

        $this->actingAs($user)
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => 'https://push.example/abc'])
            ->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example/abc']);
    }

    public function test_marking_a_child_off_notifies_a_push_subscribed_guardian(): void
    {
        Notification::fake();

        $child = Child::factory()->create();
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => null]);
        $guardian->updatePushSubscription('https://push.example/g', 'k', 'a');
        $child->guardians()->attach($guardian);

        $departure = DailyDeparture::factory()->create([
            'child_id' => $child->id,
            'date' => today()->toDateString(),
            'left_at' => null,
        ]);
        $departure->update(['status' => DepartureStatus::PickedUp, 'left_at' => now()]);

        Notification::assertSentTo(
            $guardian,
            ChildDeparted::class,
            fn ($notification, array $channels) => in_array(WebPushChannel::class, $channels, true),
        );
    }

    public function test_creating_an_excursion_pushes_to_subscribed_guardians(): void
    {
        Notification::fake();
        Http::fake(); // the Slack announce job

        $child = Child::factory()->create();
        $guardian = User::factory()->create(['role' => UserRole::Parent]);
        $guardian->updatePushSubscription('https://push.example/x', 'k', 'a');
        $child->guardians()->attach($guardian);

        $unsubscribed = User::factory()->create(['role' => UserRole::Parent]);
        $child->guardians()->attach($unsubscribed);

        Excursion::factory()->create();

        Notification::assertSentTo($guardian, NewExcursion::class);
        Notification::assertNotSentTo($unsubscribed, NewExcursion::class);
    }
}
