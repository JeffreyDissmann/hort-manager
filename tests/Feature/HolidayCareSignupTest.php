<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Signing up for Ferienbetreuung. The sign-up *is* the child's plan for that day —
 * a DailyDeparture — so a care day behaves like any other Hort day from then on.
 */
class HolidayCareSignupTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $parent;

    private Child $child;

    private HolidayPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-20 09:00'));

        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->parent = User::factory()->create(['role' => UserRole::Parent]);
        $this->child = Child::factory()->create(['name' => 'Mia']);
        $this->parent->children()->attach($this->child);

        $this->period = HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'registration_deadline' => '2026-07-31',
        ]);
        $this->period->generateCareDays();
    }

    /** @return array<int, int> the ids of the period's days, Monday first */
    private function dayIds(): array
    {
        return $this->period->careDays()->orderBy('date')->pluck('id')->all();
    }

    private function signUp(array $dayIds, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->parent)
            ->patch(route('care.update', $this->period), [
                'child_id' => $this->child->id,
                'day_ids' => $dayIds,
            ]);
    }

    public function test_signing_up_plans_the_day_like_any_other(): void
    {
        [$monday, $tuesday] = $this->dayIds();

        $this->signUp([$monday, $tuesday])->assertRedirect();

        $this->assertDatabaseCount('daily_departures', 2);
        $departure = DailyDeparture::where('date', '2026-08-03')->first();
        $this->assertSame('16:30', substr((string) $departure->planned_time, 0, 5));
        $this->assertSame(DepartureStatus::Present, $departure->status);
    }

    public function test_the_method_comes_from_the_stammplan(): void
    {
        // Mia walks home alone on Mondays during term time.
        WeeklySchedule::create([
            'child_id' => $this->child->id,
            'weekday' => 1,
            'planned_time' => '15:00',
            'method' => DepartureMethod::SentHome,
        ]);

        $this->signUp($this->dayIds())->assertRedirect();

        $this->assertSame(
            DepartureMethod::SentHome,
            DailyDeparture::where('date', '2026-08-03')->first()->planned_method,
        );
    }

    public function test_a_weekday_without_a_stammplan_entry_borrows_another_days_method(): void
    {
        // Only a Friday entry — Monday is „Hortfrei" during term time.
        WeeklySchedule::create([
            'child_id' => $this->child->id,
            'weekday' => 5,
            'planned_time' => '15:00',
            'method' => DepartureMethod::SentHome,
        ]);

        $this->signUp($this->dayIds())->assertRedirect();

        $this->assertSame(
            DepartureMethod::SentHome,
            DailyDeparture::where('date', '2026-08-03')->first()->planned_method,
        );
    }

    public function test_a_child_without_a_stammplan_defaults_to_being_picked_up(): void
    {
        $this->signUp($this->dayIds())->assertRedirect();

        $this->assertSame(
            DepartureMethod::PickedUp,
            DailyDeparture::where('date', '2026-08-03')->first()->planned_method,
        );
    }

    public function test_unticking_a_day_withdraws_the_child(): void
    {
        $days = $this->dayIds();
        $this->signUp($days)->assertRedirect();
        $this->assertDatabaseCount('daily_departures', 5);

        // Keep only Monday.
        $this->signUp([$days[0]])->assertRedirect();

        $this->assertDatabaseCount('daily_departures', 1);
        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-03']);
    }

    public function test_re_saving_does_not_overwrite_a_plan_the_family_adjusted(): void
    {
        $days = $this->dayIds();
        $this->signUp($days)->assertRedirect();

        DailyDeparture::where('date', '2026-08-03')->first()->update(['planned_time' => '14:00']);

        $this->signUp($days)->assertRedirect();

        $this->assertSame(
            '14:00',
            substr((string) DailyDeparture::where('date', '2026-08-03')->first()->planned_time, 0, 5),
        );
    }

    public function test_picking_no_days_still_counts_as_an_answer(): void
    {
        $this->signUp([])->assertRedirect();

        $this->assertDatabaseEmpty('daily_departures');
        $this->assertDatabaseHas('holiday_care_answers', [
            'holiday_period_id' => $this->period->id,
            'child_id' => $this->child->id,
        ]);
    }

    public function test_a_departed_day_is_never_withdrawn(): void
    {
        $days = $this->dayIds();
        $this->signUp($days)->assertRedirect();

        // Monday happened and Mia was collected.
        DailyDeparture::where('date', '2026-08-03')->first()->update([
            'left_at' => Carbon::parse('2026-08-03 16:20'),
            'status' => DepartureStatus::PickedUp,
        ]);

        $this->signUp([])->assertRedirect();

        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-03']);
        $this->assertDatabaseCount('daily_departures', 1);
    }

    public function test_a_day_under_way_is_never_withdrawn(): void
    {
        $days = $this->dayIds();
        $this->signUp($days)->assertRedirect();

        // It's Monday morning of the Ferienbetreuung and Mia is in the Hort.
        $this->travelTo(Carbon::parse('2026-08-03 09:00'));

        // Staff re-save Tuesday only — Monday must not disappear from under the board.
        $this->signUp([$days[1]], $this->staff)->assertRedirect();

        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-03']);
        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-04']);
        $this->assertDatabaseCount('daily_departures', 2);
    }

    public function test_a_day_that_is_over_cannot_be_signed_up_for(): void
    {
        $days = $this->dayIds();

        // Wednesday of the running Ferienbetreuung: Monday is history.
        $this->travelTo(Carbon::parse('2026-08-05 09:00'));

        $this->signUp($days, $this->staff)->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', ['date' => '2026-08-03']);
        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-05']);
        $this->assertDatabaseCount('daily_departures', 3); // Wed, Thu, Fri
    }

    public function test_days_from_another_period_are_ignored(): void
    {
        $other = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-09-07',
            'ends_on' => '2026-09-08',
        ]);
        $other->generateCareDays();

        $this->signUp($other->careDays->pluck('id')->all())->assertRedirect();

        $this->assertDatabaseEmpty('daily_departures');
    }

    public function test_a_parent_cannot_sign_up_someone_elses_child(): void
    {
        $stranger = Child::factory()->create(['name' => 'Ben']);

        $this->actingAs($this->parent)
            ->patch(route('care.update', $this->period), [
                'child_id' => $stranger->id,
                'day_ids' => $this->dayIds(),
            ])
            ->assertForbidden();

        $this->assertDatabaseEmpty('daily_departures');
    }

    public function test_parents_cannot_sign_up_after_the_deadline(): void
    {
        $this->travelTo(Carbon::parse('2026-08-01 09:00'));

        $this->signUp($this->dayIds())->assertForbidden();

        $this->assertDatabaseEmpty('daily_departures');
    }

    public function test_staff_may_still_sign_someone_up_after_the_deadline(): void
    {
        $this->travelTo(Carbon::parse('2026-08-01 09:00'));

        $this->signUp($this->dayIds(), $this->staff)->assertRedirect();

        $this->assertDatabaseCount('daily_departures', 5);
    }

    public function test_the_page_shows_a_parents_own_children_and_their_picks(): void
    {
        [$monday] = $this->dayIds();
        $this->signUp([$monday])->assertRedirect();

        Child::factory()->create(['name' => 'Fremdes Kind']);

        $this->actingAs($this->parent)
            ->get(route('care.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Care/Index')
                ->count('children', 1)
                ->where('children.0.name', 'Mia')
                ->where('periods.0.days.0.children', [$this->child->id])
                ->where('periods.0.days.1.children', [])
                ->where('periods.0.answered', [$this->child->id])
                ->where('periods.0.open', true)
                ->where('canOverrideDeadline', false)
            );
    }

    public function test_staff_see_every_child_and_may_override_the_deadline(): void
    {
        Child::factory()->create(['name' => 'Ben']);

        $this->actingAs($this->staff)
            ->get(route('care.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->count('children', 2)
                ->where('canOverrideDeadline', true)
            );
    }

    public function test_finished_periods_are_not_offered(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 09:00'));

        $this->actingAs($this->parent)
            ->get(route('care.index'))
            ->assertInertia(fn (Assert $page) => $page->count('periods', 0));
    }

    public function test_a_closure_is_not_a_care_period(): void
    {
        $closure = HolidayPeriod::factory()->create(['starts_on' => '2026-08-10', 'ends_on' => '2026-08-14']);

        $this->actingAs($this->parent)
            ->patch(route('care.update', $closure), ['child_id' => $this->child->id, 'day_ids' => []])
            ->assertNotFound();
    }
}
