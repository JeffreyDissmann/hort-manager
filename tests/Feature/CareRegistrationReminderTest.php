<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Child;
use App\Models\HolidayCareAnswer;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Notifications\CareRegistrationReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The Slack/push nudge to answer an open Ferienbetreuung. */
class CareRegistrationReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(Carbon::parse('2026-07-24 08:00'));

        $this->parent = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U-PARENT']);
        $this->child = Child::factory()->create(['name' => 'Mia']);
        $this->parent->children()->attach($this->child);
    }

    /** Deadline `$days` from today (0 = today, 7 = a week out). */
    private function period(int $days, string $name = 'Sommer-Ferienbetreuung'): HolidayPeriod
    {
        return HolidayPeriod::factory()->care()->create([
            'name' => $name,
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'registration_deadline' => Carbon::today()->addDays($days)->toDateString(),
        ]);
    }

    public function test_it_reminds_on_the_anmeldeschluss(): void
    {
        $this->period(0);

        $this->artisan('care:remind-open')->assertSuccessful();

        Notification::assertSentTo($this->parent, CareRegistrationReminder::class, function ($n) {
            return $n->childNames === ['Mia'];
        });
    }

    public function test_it_stays_quiet_before_the_deadline(): void
    {
        $this->period(3);

        $this->artisan('care:remind-open')->assertSuccessful();

        // Creating the period announces it, so assert on the reminder specifically.
        Notification::assertNotSentTo($this->parent, CareRegistrationReminder::class);
    }

    public function test_it_stays_quiet_after_the_deadline(): void
    {
        $this->period(-1);

        $this->artisan('care:remind-open')->assertSuccessful();

        // Creating the period announces it, so assert on the reminder specifically.
        Notification::assertNotSentTo($this->parent, CareRegistrationReminder::class);
    }

    public function test_a_family_that_answered_is_left_alone(): void
    {
        $period = $this->period(0);
        HolidayCareAnswer::create([
            'holiday_period_id' => $period->id,
            'child_id' => $this->child->id,
            'answered_by' => $this->parent->id,
            'answered_at' => now(),
        ]);

        $this->artisan('care:remind-open')->assertSuccessful();

        // Creating the period announces it, so assert on the reminder specifically.
        Notification::assertNotSentTo($this->parent, CareRegistrationReminder::class);
    }

    public function test_one_dm_lists_all_of_a_guardians_children(): void
    {
        $second = Child::factory()->create(['name' => 'Ben']);
        $this->parent->children()->attach($second);
        $this->period(0);

        $this->artisan('care:remind-open')->assertSuccessful();

        Notification::assertSentToTimes($this->parent, CareRegistrationReminder::class, 1);
        Notification::assertSentTo($this->parent, CareRegistrationReminder::class, function ($n) {
            return $n->childNames === ['Ben', 'Mia'];
        });
    }

    public function test_a_period_without_a_deadline_is_never_chased(): void
    {
        HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'registration_deadline' => null,
        ]);

        $this->artisan('care:remind-open')->assertSuccessful();

        // Creating the period announces it, so assert on the reminder specifically.
        Notification::assertNotSentTo($this->parent, CareRegistrationReminder::class);
    }

    public function test_a_child_not_enrolled_during_the_period_is_ignored(): void
    {
        $this->period(0);
        $this->child->update(['active_until' => '2026-07-30']);

        $this->artisan('care:remind-open')->assertSuccessful();

        // Creating the period announces it, so assert on the reminder specifically.
        Notification::assertNotSentTo($this->parent, CareRegistrationReminder::class);
    }

    public function test_an_unreachable_guardian_gets_nothing(): void
    {
        // No Slack id and no push subscription → nothing to deliver on.
        $this->parent->slack_id = null;
        $this->parent->save();
        $this->period(0);

        $this->artisan('care:remind-open')->assertSuccessful();

        // Creating the period announces it, so assert on the reminder specifically.
        Notification::assertNotSentTo($this->parent, CareRegistrationReminder::class);
    }

    public function test_a_closure_is_never_chased(): void
    {
        HolidayPeriod::factory()->create([
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'registration_deadline' => Carbon::today()->toDateString(),
        ]);

        $this->artisan('care:remind-open')->assertSuccessful();

        // Creating the period announces it, so assert on the reminder specifically.
        Notification::assertNotSentTo($this->parent, CareRegistrationReminder::class);
    }

    public function test_dry_run_sends_nothing(): void
    {
        $this->period(0);

        $this->artisan('care:remind-open --dry-run')
            ->expectsOutputToContain('Mia')
            ->assertSuccessful();

        // Creating the period announces it, so assert on the reminder specifically.
        Notification::assertNotSentTo($this->parent, CareRegistrationReminder::class);
    }

    public function test_the_reminder_is_a_guardian_category_and_respects_the_toggle(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
        $period = $this->period(7);
        $notification = new CareRegistrationReminder($period, ['Mia']);

        $this->assertContains('slack', $notification->via($this->parent));

        $this->parent->notification_preferences = ['care_registration' => ['slack' => false, 'push' => false]];
        $this->parent->save();

        $this->assertSame([], $notification->via($this->parent->fresh()));
    }
}
