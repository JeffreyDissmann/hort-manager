<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\HolidayPeriodType;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Notifications\CareRegistrationOpened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A new Ferienbetreuung is announced the way a new Ausflug is — waiting for the
 * Anmeldeschluss reminder would otherwise leave weeks of silence.
 */
class CareAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(Carbon::parse('2026-07-20 09:00'));

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'slack_id' => 'U-STAFF']);
        $this->parent = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U-PARENT']);
        $this->parent->children()->attach(Child::factory()->create(['name' => 'Mia']));
    }

    /** @param  array<string, mixed>  $overrides */
    private function create(array $overrides = []): void
    {
        $this->actingAs($this->staff)->post(route('closures.store'), array_merge([
            'name' => 'Sommer-Ferienbetreuung',
            'type' => HolidayPeriodType::Care->value,
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'registration_deadline' => '2026-07-31',
        ], $overrides))->assertRedirect();
    }

    public function test_creating_a_ferienbetreuung_tells_the_guardians(): void
    {
        $this->create();

        Notification::assertSentTo($this->parent, CareRegistrationOpened::class, function (CareRegistrationOpened $n) {
            return $n->period->name === 'Sommer-Ferienbetreuung';
        });
    }

    public function test_a_closure_announces_nothing(): void
    {
        $this->actingAs($this->staff)->post(route('closures.store'), [
            'name' => 'Sommerferien',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
        ])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_a_guardian_without_slack_or_push_is_skipped(): void
    {
        $unreachable = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => null]);
        $unreachable->children()->attach(Child::factory()->create());

        $this->create();

        Notification::assertNothingSentTo($unreachable);
    }

    public function test_a_user_without_children_is_not_told(): void
    {
        $childless = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U-NOKIDS']);

        $this->create();

        Notification::assertNothingSentTo($childless);
    }

    public function test_editing_a_period_does_not_announce_it_again(): void
    {
        $this->create();
        Notification::assertSentToTimes($this->parent, CareRegistrationOpened::class, 1);

        $period = HolidayPeriod::first();
        $this->actingAs($this->staff)->patch(route('closures.update', $period), [
            'name' => 'Sommer-Ferienbetreuung',
            'type' => HolidayPeriodType::Care->value,
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-12',
            'registration_deadline' => '2026-07-31',
        ])->assertRedirect();

        Notification::assertSentToTimes($this->parent, CareRegistrationOpened::class, 1);
    }

    public function test_the_announcement_shares_the_care_toggle(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
        $period = HolidayPeriod::factory()->care()->create();
        $notification = new CareRegistrationOpened($period);

        $this->assertContains('slack', $notification->via($this->parent));

        $this->parent->notification_preferences = ['care_registration' => ['slack' => false, 'push' => false]];
        $this->parent->save();

        $this->assertSame([], $notification->via($this->parent->fresh()));
    }
}
