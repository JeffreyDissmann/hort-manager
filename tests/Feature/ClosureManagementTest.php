<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\HolidayPeriodType;
use App\Enums\UserRole;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Managing Schließzeiten: staff-only writes, open reads. */
class ClosureManagementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    private function parent(): User
    {
        return User::factory()->create(['role' => UserRole::Parent]);
    }

    public function test_staff_can_add_a_closure(): void
    {
        $this->actingAs($this->staff())
            ->post(route('closures.store'), [
                'name' => 'Sommerferien',
                'starts_on' => '2026-08-03',
                'ends_on' => '2026-08-14',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('holiday_periods', [
            'name' => 'Sommerferien',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-14',
            'type' => HolidayPeriodType::Closed->value,
        ]);
    }

    public function test_a_single_day_closure_is_allowed(): void
    {
        $this->actingAs($this->staff())
            ->post(route('closures.store'), [
                'name' => 'Brückentag',
                'starts_on' => '2026-05-15',
                'ends_on' => '2026-05-15',
            ])
            ->assertRedirect();

        $this->assertSame(1, HolidayPeriod::first()->dayCount());
    }

    public function test_the_end_cannot_be_before_the_start(): void
    {
        $this->actingAs($this->staff())
            ->post(route('closures.store'), [
                'name' => 'Kaputt',
                'starts_on' => '2026-08-10',
                'ends_on' => '2026-08-03',
            ])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_a_name_and_both_dates_are_required(): void
    {
        $this->actingAs($this->staff())
            ->post(route('closures.store'), [])
            ->assertSessionHasErrors(['name', 'starts_on', 'ends_on']);
    }

    public function test_staff_can_edit_and_delete_a_closure(): void
    {
        $closure = HolidayPeriod::factory()->create(['name' => 'Herbstferien']);
        $staff = $this->staff();

        $this->actingAs($staff)
            ->patch(route('closures.update', $closure), [
                'name' => 'Herbstferien (verlängert)',
                'starts_on' => $closure->starts_on->toDateString(),
                'ends_on' => $closure->ends_on->addDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('Herbstferien (verlängert)', $closure->fresh()->name);

        $this->actingAs($staff)->delete(route('closures.destroy', $closure))->assertRedirect();
        $this->assertDatabaseEmpty('holiday_periods');
    }

    public function test_parents_can_see_closures_but_not_change_them(): void
    {
        $closure = HolidayPeriod::factory()->create(['name' => 'Weihnachtsferien']);
        $parent = $this->parent();

        $this->actingAs($parent)
            ->get(route('closures.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Closures/Index')
                ->where('canManage', false)
                ->where('upcoming.0.name', 'Weihnachtsferien')
            );

        $this->actingAs($parent)
            ->post(route('closures.store'), [
                'name' => 'Selbst gemacht',
                'starts_on' => '2026-08-03',
                'ends_on' => '2026-08-04',
            ])
            ->assertForbidden();

        $this->actingAs($parent)->delete(route('closures.destroy', $closure))->assertForbidden();
    }

    public function test_an_admin_in_the_parent_role_can_manage_closures(): void
    {
        // Admin is its own axis: an admin who switched themselves to „Elternteil"
        // keeps the rights, so they don't lock themselves out of the setup screens.
        $admin = User::factory()->admin()->create(['role' => UserRole::Parent]);

        $this->actingAs($admin)
            ->post(route('closures.store'), [
                'name' => 'Fortbildung',
                'starts_on' => '2026-09-14',
                'ends_on' => '2026-09-14',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('holiday_periods', ['name' => 'Fortbildung']);

        $this->actingAs($admin)
            ->get(route('closures.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
    }

    public function test_it_splits_upcoming_from_past_by_the_last_day(): void
    {
        $this->travelTo(Carbon::parse('2026-08-10'));

        // Ends today → still „upcoming", the Hort is shut right now.
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-10')->create(['name' => 'Läuft noch']);
        HolidayPeriod::factory()->between('2026-07-01', '2026-07-10')->create(['name' => 'Vorbei']);
        HolidayPeriod::factory()->between('2026-12-24', '2026-12-31')->create(['name' => 'Kommt noch']);

        $this->actingAs($this->staff())
            ->get(route('closures.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('upcoming.0.name', 'Läuft noch')
                ->where('upcoming.1.name', 'Kommt noch')
                ->count('upcoming', 2)
                ->where('past.0.name', 'Vorbei')
                ->count('past', 1)
            );
    }

    public function test_the_scopes_find_periods_by_day_and_range(): void
    {
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-07')->create();

        $this->assertTrue(HolidayPeriod::query()->covering('2026-08-03')->exists());  // first day
        $this->assertTrue(HolidayPeriod::query()->covering('2026-08-07')->exists());  // last day
        $this->assertFalse(HolidayPeriod::query()->covering('2026-08-08')->exists());

        // Overlap, not containment: a week that only clips the period still counts.
        $this->assertTrue(HolidayPeriod::query()->overlapping('2026-08-06', '2026-08-12')->exists());
        $this->assertFalse(HolidayPeriod::query()->overlapping('2026-08-08', '2026-08-14')->exists());
    }

    public function test_days_lists_every_date_inclusively(): void
    {
        $closure = HolidayPeriod::factory()->between('2026-08-03', '2026-08-05')->create();

        $this->assertSame(['2026-08-03', '2026-08-04', '2026-08-05'], $closure->days()->all());
        $this->assertSame(3, $closure->dayCount());
    }
}
