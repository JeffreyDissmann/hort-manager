<?php

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyBoardTest extends TestCase
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

    private function scheduledChild(int $weekday, string $time = '16:00'): Child
    {
        $child = Child::factory()->create();
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => $weekday,
            'planned_time' => $time,
            'method' => DepartureMethod::PickedUp,
        ]);

        return $child;
    }

    public function test_board_seeds_a_row_from_the_stammplan_for_today(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = $this->scheduledChild(weekday: 1, time: '16:00');

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Board/Index')
                ->where('date.is_today', true)
                ->where('canMark', true)
                ->has('rows', 1)
                ->where('rows.0.name', $child->name)
                ->where('rows.0.planned_time', '16:00')
                ->where('rows.0.status', DepartureStatus::Present->value)
                ->where('rows.0.is_overridden', false)
            );

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'status' => DepartureStatus::Present->value,
        ]);
    }

    public function test_children_not_scheduled_that_day_are_not_on_the_board(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $this->scheduledChild(weekday: 3); // Wednesday only

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    }

    public function test_on_the_weekend_the_board_targets_the_next_weekday(): void
    {
        $this->travelTo(Carbon::parse('2026-06-27')); // Saturday
        $this->scheduledChild(weekday: 1); // Monday

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('date.iso', '2026-06-29') // next Monday
                ->where('date.is_today', false)
                ->has('rows', 1)
            );
    }

    public function test_staff_can_mark_a_child_as_picked_up_and_undo(): void
    {
        $staff = $this->staff();
        $departure = DailyDeparture::factory()->create(['status' => DepartureStatus::Present]);

        $this->actingAs($staff)
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::PickedUp->value])
            ->assertRedirect();

        $departure->refresh();
        $this->assertSame(DepartureStatus::PickedUp, $departure->status);
        $this->assertNotNull($departure->left_at);
        $this->assertSame($staff->id, $departure->marked_by);

        // Undo back to present clears the timestamp and marker.
        $this->actingAs($staff)
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::Present->value]);

        $departure->refresh();
        $this->assertSame(DepartureStatus::Present, $departure->status);
        $this->assertNull($departure->left_at);
        $this->assertNull($departure->marked_by);
    }

    public function test_parents_cannot_mark_departures(): void
    {
        $departure = DailyDeparture::factory()->create();

        $this->actingAs($this->parent())
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::PickedUp->value])
            ->assertForbidden();
    }

    public function test_a_parent_can_override_their_own_childs_plan_today(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        $departure = DailyDeparture::factory()->create([
            'child_id' => $child->id,
            'planned_time' => '16:00',
        ]);

        $this->actingAs($parent)
            ->patch(route('board.override', $departure), [
                'planned_time' => '14:30',
                'planned_method' => DepartureMethod::PickedUp->value,
            ])
            ->assertRedirect();

        $this->assertSame('14:30', substr((string) $departure->refresh()->planned_time, 0, 5));
    }

    public function test_a_parent_cannot_override_another_childs_plan(): void
    {
        $departure = DailyDeparture::factory()->create(); // not their child

        $this->actingAs($this->parent())
            ->patch(route('board.override', $departure), ['planned_time' => '14:30'])
            ->assertForbidden();
    }

    public function test_an_override_is_flagged_on_the_board(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = $this->scheduledChild(weekday: 1, time: '16:00');

        // Seed today's row, then override it to an earlier time.
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'planned_time' => '14:30',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.planned_time', '14:30')
                ->where('rows.0.is_overridden', true)
            );
    }
}
