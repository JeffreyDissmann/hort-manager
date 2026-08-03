<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LateChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Staff get a DM when a parent still changes today's plan after the Hort-wide cutoff. */
class LateChangeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $parent;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Monday, well after the default 12:00 cutoff.
        $this->travelTo(Carbon::parse('2026-06-22 15:00'));

        // Reachable = has Slack or a push subscription; otherwise no one is notified.
        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'slack_id' => 'U-STAFF']);
        $this->parent = User::factory()->create(['role' => UserRole::Parent]);
        $this->child = Child::factory()->create();
        $this->parent->children()->attach($this->child);
    }

    /** @param  array<string, mixed>  $overrides */
    private function adjust(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->parent)->patch(route('weekly-plan.adjust'), array_merge([
            'child_id' => $this->child->id,
            'date' => '2026-06-22',
            'planned_time' => '15:30',
            'planned_method' => 'picked_up',
        ], $overrides));
    }

    public function test_a_parent_changing_today_after_the_cutoff_notifies_staff(): void
    {
        $this->adjust()->assertRedirect();

        Notification::assertSentTo($this->staff, LateChange::class, function (LateChange $notification) {
            return $notification->child->is($this->child)
                && $notification->actor->is($this->parent);
        });
    }

    public function test_a_change_before_the_cutoff_notifies_nobody(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22 09:00'));

        $this->adjust()->assertRedirect();

        Notification::assertNothingSentTo($this->staff);
    }

    public function test_the_cutoff_is_the_configured_one(): void
    {
        Setting::set(Setting::LateChangeCutoff, '16:00');

        // 15:00 is now before the cutoff.
        $this->adjust()->assertRedirect();

        Notification::assertNothingSentTo($this->staff);
    }

    public function test_a_change_for_a_later_day_is_never_late(): void
    {
        $this->adjust(['date' => '2026-06-23'])->assertRedirect();

        Notification::assertNothingSentTo($this->staff);
    }

    public function test_staff_changing_a_plan_themselves_notifies_nobody(): void
    {
        $this->actingAs($this->staff)->patch(route('weekly-plan.adjust'), [
            'child_id' => $this->child->id,
            'date' => '2026-06-22',
            'planned_time' => '15:30',
            'planned_method' => 'picked_up',
        ])->assertRedirect();

        Notification::assertNothingSentTo($this->staff);
    }

    public function test_re_saving_an_unchanged_plan_notifies_nobody(): void
    {
        $this->adjust()->assertRedirect();
        Notification::assertSentToTimes($this->staff, LateChange::class, 1);

        $this->adjust()->assertRedirect();
        Notification::assertSentToTimes($this->staff, LateChange::class, 1);
    }

    public function test_resetting_a_day_to_the_standard_plan_notifies_staff(): void
    {
        $this->adjust()->assertRedirect();

        $this->actingAs($this->parent)->patch(route('weekly-plan.reset'), [
            'child_id' => $this->child->id,
            'date' => '2026-06-22',
        ])->assertRedirect();

        Notification::assertSentToTimes($this->staff, LateChange::class, 2);
    }

    public function test_reporting_todays_absence_after_the_cutoff_notifies_staff(): void
    {
        $this->actingAs($this->parent)->post(route('absences.store'), [
            'child_id' => $this->child->id,
            'from' => '2026-06-22',
            'to' => '2026-06-22',
            'reason' => 'sick',
        ])->assertRedirect();

        Notification::assertSentTo($this->staff, LateChange::class);
    }

    public function test_an_absence_range_notifies_once_for_today_only(): void
    {
        $this->actingAs($this->parent)->post(route('absences.store'), [
            'child_id' => $this->child->id,
            'from' => '2026-06-22',
            'to' => '2026-06-24',
            'reason' => 'sick',
        ])->assertRedirect();

        Notification::assertSentToTimes($this->staff, LateChange::class, 1);
    }

    public function test_clearing_todays_absence_after_the_cutoff_notifies_staff(): void
    {
        Absence::report($this->child, '2026-06-22', AbsenceReason::Sick, $this->parent->id);

        $this->actingAs($this->parent)->delete(route('absences.destroy'), [
            'child_id' => $this->child->id,
            'from' => '2026-06-22',
            'to' => '2026-06-22',
        ])->assertRedirect();

        Notification::assertSentTo($this->staff, LateChange::class, function (LateChange $notification) {
            return $notification->summary === 'kommt doch';
        });
    }

    public function test_a_same_day_board_override_notifies_staff(): void
    {
        $departure = DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-06-22',
            'status' => DepartureStatus::Present,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
        ]);

        $this->actingAs($this->parent)->patch(route('board.override', $departure), [
            'planned_time' => '16:15',
            'planned_method' => 'picked_up',
        ])->assertRedirect();

        Notification::assertSentTo($this->staff, LateChange::class);
    }

    public function test_switching_to_a_companion_after_the_cutoff_notifies_staff(): void
    {
        // with_child takes a different path through update() (no time, confirmation
        // handling), so it needs its own check that the DM still goes out.
        $companion = Child::factory()->create(['name' => 'Ben']);
        DailyDeparture::create([
            'child_id' => $companion->id,
            'date' => '2026-06-22',
            'status' => DepartureStatus::Present,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
        ]);

        $this->adjust([
            'planned_method' => 'with_child',
            'planned_time' => null,
            'companion_child_id' => $companion->id,
        ])->assertRedirect();

        Notification::assertSentTo($this->staff, LateChange::class, function (LateChange $notification) {
            return $notification->summary === 'geht mit Ben mit';
        });
    }

    public function test_the_late_change_toggle_gates_the_slack_channel(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
        $notification = new LateChange($this->child, $this->parent, '15:30');

        $this->assertContains('slack', $notification->via($this->staff));

        $this->staff->notification_preferences = ['late_change' => ['slack' => false, 'push' => false]];
        $this->staff->save();

        $this->assertSame([], $notification->via($this->staff->fresh()));
    }

    public function test_parents_are_never_notified(): void
    {
        $this->adjust()->assertRedirect();

        Notification::assertNothingSentTo($this->parent);
    }
}
