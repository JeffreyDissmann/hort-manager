<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\User;
use App\Notifications\WeeklyDigest;
use App\Support\WeeklyDigestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The Monday „Wochenüberblick": the builder's per-parent data and the send command. */
class WeeklyDigestTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();
        // A fixed Monday so the week is deterministic.
        $this->monday = Carbon::parse('2026-06-22'); // Monday
        Carbon::setTestNow($this->monday->copy()->setTime(12, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_builder_summarises_only_the_parents_own_children(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $mine = Child::factory()->create(['name' => 'Mia']);
        $other = Child::factory()->create(['name' => 'Otto']);
        $parent->children()->attach($mine);

        // Mia: a Monday pickup, a Tuesday absence, a Wednesday excursion.
        $mine->weeklySchedules()->create(['weekday' => 1, 'planned_time' => '15:00', 'method' => DepartureMethod::PickedUp]);
        Absence::create(['child_id' => $mine->id, 'date' => '2026-06-23', 'reason' => AbsenceReason::Sick]);

        $excursion = Excursion::factory()->create(['name' => 'Zoo', 'date' => '2026-06-24']);
        $excursion->children()->attach($mine->id, ['response' => true]);
        $excursion->children()->attach($other->id, ['response' => true]);

        DailyProgram::create(['date' => '2026-06-22', 'lunch' => 'Nudeln', 'activity' => 'Basteln']);

        $digest = WeeklyDigestBuilder::for($parent, $this->monday);

        // Only Mia is summarised.
        $this->assertCount(1, $digest['children']);
        $this->assertSame('Mia', $digest['children'][0]['name']);

        $days = $digest['children'][0]['days'];
        $this->assertStringContainsString('15:00', $days[0]['summary']);      // Mon pickup
        $this->assertSame(AbsenceReason::Sick->label(), $days[1]['summary']); // Tue absence
        $this->assertStringContainsString('Zoo', $days[2]['summary']);        // Wed excursion

        // Hort-wide program is present.
        $this->assertSame('Nudeln', $digest['program'][0]['lunch']);
        $this->assertSame('Basteln', $digest['program'][0]['activity']);
        $this->assertSame('Zoo', $digest['excursions'][0]['name']);
    }

    public function test_command_sends_to_reachable_guardians_who_still_want_it(): void
    {
        Notification::fake();

        $child = Child::factory()->create();
        $reachable = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $child->guardians()->attach($reachable);

        $this->artisan('weekly:digest')->assertSuccessful();

        Notification::assertSentTo($reachable, WeeklyDigest::class);
    }

    public function test_command_skips_a_guardian_who_opted_out_of_both_channels(): void
    {
        Notification::fake();

        $child = Child::factory()->create();
        $optedOut = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $optedOut->notification_preferences = ['weekly_digest' => ['slack' => false, 'push' => false]];
        $optedOut->save();
        $child->guardians()->attach($optedOut);

        $this->artisan('weekly:digest')->assertSuccessful();

        Notification::assertNotSentTo($optedOut, WeeklyDigest::class);
    }

    public function test_command_skips_unreachable_and_non_guardian_users(): void
    {
        Notification::fake();

        // Guardian but unreachable (no slack, no push).
        $child = Child::factory()->create();
        $unreachable = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => null]);
        $child->guardians()->attach($unreachable);

        // Reachable but not a guardian.
        $childless = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U9']);

        $this->artisan('weekly:digest')->assertSuccessful();

        Notification::assertNotSentTo($unreachable, WeeklyDigest::class);
        Notification::assertNotSentTo($childless, WeeklyDigest::class);
    }

    public function test_dry_run_lists_recipients_and_sends_nothing(): void
    {
        Notification::fake();

        $child = Child::factory()->create();
        $guardian = User::factory()->create(['name' => 'Mum', 'role' => UserRole::Parent, 'slack_id' => 'U1']);
        $child->guardians()->attach($guardian);

        $this->artisan('weekly:digest', ['--dry-run' => true])
            ->expectsOutputToContain('Mum')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }
}
