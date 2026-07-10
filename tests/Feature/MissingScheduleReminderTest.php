<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\User;
use App\Notifications\ScheduleMissingReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The „no Stammplan yet" nudge: the shared banner prop for parents, and the
 * `wochenplan:remind-unset` Slack/push command.
 */
class MissingScheduleReminderTest extends TestCase
{
    use RefreshDatabase;

    private function withSchedule(Child $child): void
    {
        $child->weeklySchedules()->create([
            'weekday' => 1,
            'planned_time' => '15:00',
            'method' => DepartureMethod::PickedUp,
        ]);
    }

    public function test_the_banner_prop_lists_a_parents_unplanned_children(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $planned = Child::factory()->create(['name' => 'Planned']);
        $unplanned = Child::factory()->create(['name' => 'Unplanned']);
        $this->withSchedule($planned);
        $parent->children()->attach([$planned->id, $unplanned->id]);

        $this->actingAs($parent)
            ->get(route('children.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('childrenWithoutPlan', fn ($list) => collect($list)->pluck('name')->all() === ['Unplanned'])
            );
    }

    public function test_staff_get_no_such_banner(): void
    {
        Child::factory()->create(); // unplanned, but staff manage everyone

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('children.index'))
            ->assertInertia(fn (Assert $page) => $page->where('childrenWithoutPlan', []));
    }

    public function test_the_command_reminds_guardians_of_unplanned_children(): void
    {
        Notification::fake();

        $unplanned = Child::factory()->create();
        $planned = Child::factory()->create();
        $this->withSchedule($planned);

        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $plannedGuardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U2']);
        $unplanned->guardians()->attach($guardian);
        $planned->guardians()->attach($plannedGuardian);

        $this->artisan('wochenplan:remind-unset')->assertSuccessful();

        Notification::assertSentTo($guardian, ScheduleMissingReminder::class);
        Notification::assertNotSentTo($plannedGuardian, ScheduleMissingReminder::class);
    }

    public function test_a_dry_run_reports_but_sends_nothing(): void
    {
        Notification::fake();

        $child = Child::factory()->create(['name' => 'Emma']);
        $guardian = User::factory()->create(['name' => 'Mum', 'role' => UserRole::Parent, 'slack_id' => 'U1']);
        $child->guardians()->attach($guardian);

        $this->artisan('wochenplan:remind-unset', ['--dry-run' => true])
            ->expectsOutputToContain('Emma → Mum')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_command_skips_unreachable_guardians(): void
    {
        Notification::fake();

        $child = Child::factory()->create();
        // No slack_id and no push subscription → not reachable.
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => null]);
        $child->guardians()->attach($guardian);

        $this->artisan('wochenplan:remind-unset')->assertSuccessful();

        Notification::assertNotSentTo($guardian, ScheduleMissingReminder::class);
    }
}
