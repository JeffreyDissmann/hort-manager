<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** „Datenpflege" — admin-only, and about today rather than about a period. */
class DataUpkeepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
    }

    public function test_it_is_closed_to_everyone_but_admins(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('data-upkeep'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('data-upkeep'))
            ->assertForbidden();
    }

    public function test_it_counts_the_gaps_as_they_stand_today(): void
    {
        // One child properly set up, one with a guardian but no plan, one with neither.
        $planned = Child::factory()->create();
        $planned->weeklySchedules()->create(['weekday' => 1, 'planned_time' => '15:00']);
        $parent = User::factory()->create(['slack_id' => null]);
        $parent->children()->attach([$planned->id, Child::factory()->create()->id]);
        Child::factory()->create();

        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('data-upkeep'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DataUpkeep/Index')
                ->where('gaps.children_without_plan', 2)
                ->where('gaps.children_without_guardian', 1)
                ->where('gaps.guardians_without_slack', 1)
                ->where('gaps.open_excursion_answers', 0));
    }

    public function test_an_account_belonging_to_nobody_is_a_gap(): void
    {
        // A parent whose child has long left; staff and admins are never „orphaned",
        // whether or not they have a child of their own.
        User::factory()->create();
        User::factory()->staff()->create();
        User::factory()->admin()->create();

        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('data-upkeep'))
            ->assertInertia(fn (Assert $page) => $page->where('gaps.orphaned_accounts', 1));
    }

    public function test_it_reports_what_is_stored_and_how_far_back(): void
    {
        $child = Child::factory()->create();
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-02-08',
            'planned_time' => '15:00',
        ]);
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-07-01',
            'planned_time' => '15:00',
        ]);

        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('data-upkeep'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('inventory.records.0.key', 'departures')
                ->where('inventory.records.0.count', 2)
                // The oldest entry is what an Aufbewahrungsfrist would bite into first.
                ->where('inventory.records.0.oldest', '2026-02-08')
                ->where('inventory.children_active', 1)
                // :memory: in tests, so there is no file to size — and no crash either.
                ->where('inventory.database_bytes', null));
    }

    public function test_a_child_who_has_left_is_not_a_gap(): void
    {
        // No Stammplan, but they stopped coming in June — nothing to fix there.
        Child::factory()->create(['active_from' => '2025-09-01', 'active_until' => '2026-06-30']);

        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('data-upkeep'))
            ->assertInertia(fn (Assert $page) => $page->where('gaps.children_without_plan', 0));
    }

    public function test_an_admin_changes_how_long_records_are_kept(): void
    {
        $this->actingAs(User::factory()->staff()->admin()->create())
            ->patch(route('data-upkeep.retention'), ['retention_months' => 12])
            ->assertRedirect();

        $this->assertSame(12, Setting::retentionMonths());
    }

    public function test_only_the_offered_periods_are_accepted(): void
    {
        // Otherwise a hand-crafted request could set „1 month" on a whim and quietly
        // delete two years of records that night.
        $this->actingAs(User::factory()->staff()->admin()->create())
            ->patch(route('data-upkeep.retention'), ['retention_months' => 7])
            ->assertSessionHasErrors('retention_months');
    }

    public function test_changing_the_period_needs_an_admin(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->patch(route('data-upkeep.retention'), ['retention_months' => 12])
            ->assertForbidden();

        $this->assertSame(Setting::DefaultRetentionMonths, Setting::retentionMonths());
    }

    public function test_it_counts_unanswered_invitations_to_coming_trips(): void
    {
        $child = Child::factory()->create();
        // Creating a trip invites every enrolled child; nobody has answered yet.
        $upcoming = Excursion::factory()->create(['date' => '2026-08-20']);
        $upcoming->children()->syncWithoutDetaching([$child->id]);

        // A past trip's silence can't be fixed any more, so it doesn't count.
        $past = Excursion::factory()->create(['date' => '2026-07-01']);
        $past->children()->syncWithoutDetaching([$child->id]);

        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('data-upkeep'))
            ->assertInertia(fn (Assert $page) => $page->where('gaps.open_excursion_answers', 1));
    }
}
