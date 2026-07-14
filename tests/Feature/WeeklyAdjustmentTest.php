<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeeklyAdjustmentTest extends TestCase
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

    /** A current-week weekday that is today or later. */
    private function upcomingWeekday(): string
    {
        // Freeze to Monday so the whole work week is "today or later".
        Carbon::setTestNow(Carbon::parse('2026-06-22')); // Monday

        return '2026-06-24'; // Wednesday of the same week
    }

    public function test_a_parent_can_adjust_their_own_childs_day(): void
    {
        $date = $this->upcomingWeekday();
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_time' => '14:30',
                'planned_method' => DepartureMethod::PickedUp->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => $date,
            'planned_time' => '14:30',
        ]);
    }

    public function test_an_adjustment_can_carry_a_comment(): void
    {
        $date = $this->upcomingWeekday();
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_time' => '14:30',
                'planned_method' => DepartureMethod::PickedUp->value,
                'note' => 'wegen Arzttermin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => $date,
            'note' => 'wegen Arzttermin',
        ]);
    }

    public function test_a_sent_home_adjustment_keeps_its_time_qualifier(): void
    {
        $date = $this->upcomingWeekday();
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_time' => '15:00',
                'planned_method' => DepartureMethod::SentHome->value,
                'time_qualifier' => TimeQualifier::From->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => $date,
            'planned_method' => DepartureMethod::SentHome->value,
            'time_qualifier' => TimeQualifier::From->value,
        ]);
    }

    public function test_a_bis_time_qualifier_is_stored(): void
    {
        $date = $this->upcomingWeekday();
        $child = Child::factory()->create();

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_time' => '15:00',
                'planned_method' => DepartureMethod::SentHome->value,
                'time_qualifier' => TimeQualifier::By->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => $date,
            'time_qualifier' => TimeQualifier::By->value,
        ]);
    }

    public function test_the_time_qualifier_is_dropped_for_a_picked_up_adjustment(): void
    {
        $date = $this->upcomingWeekday();
        $child = Child::factory()->create();

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_time' => '15:00',
                'planned_method' => DepartureMethod::PickedUp->value,
                'time_qualifier' => TimeQualifier::From->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => $date,
            'planned_method' => DepartureMethod::PickedUp->value,
            'time_qualifier' => null,
        ]);
    }

    public function test_a_pickup_requires_a_method(): void
    {
        $date = $this->upcomingWeekday();
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_time' => '14:30',
                // no method
            ])
            ->assertSessionHasErrors('planned_method');

        $this->assertDatabaseMissing('daily_departures', ['child_id' => $child->id, 'date' => $date]);
    }

    public function test_a_picked_up_pickup_requires_a_time(): void
    {
        $date = $this->upcomingWeekday();
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_method' => DepartureMethod::PickedUp->value,
                // no time
            ])
            ->assertSessionHasErrors('planned_time');
    }

    public function test_a_companion_pickup_needs_no_own_time(): void
    {
        $date = $this->upcomingWeekday();
        $parent = $this->parent();
        $tom = Child::factory()->create(['name' => 'Tom']);
        $parent->children()->attach($tom);
        // Emma is picked up that day, so she can be a companion.
        $emma = Child::factory()->create(['name' => 'Emma']);
        DailyDeparture::create([
            'child_id' => $emma->id,
            'date' => $date,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $tom->id,
                'date' => $date,
                'planned_method' => DepartureMethod::WithChild->value,
                'companion_child_id' => $emma->id,
                // no planned_time — mirrored from Emma
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_a_parent_cannot_adjust_another_childs_day(): void
    {
        $date = $this->upcomingWeekday();
        $child = Child::factory()->create(); // not linked

        $this->actingAs($this->parent())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_time' => '14:30',
                'planned_method' => DepartureMethod::PickedUp->value,
            ])
            ->assertForbidden();
    }

    public function test_past_days_cannot_be_adjusted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24')); // Wednesday
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => '2026-06-22', // Monday, already past
                'planned_time' => '14:30',
                'planned_method' => DepartureMethod::PickedUp->value,
            ])
            ->assertForbidden();
    }

    public function test_reset_removes_the_override(): void
    {
        $date = $this->upcomingWeekday();
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $date,
            'planned_time' => '14:30',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($parent)
            ->patch(route('weekly-plan.reset'), ['child_id' => $child->id, 'date' => $date])
            ->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', [
            'child_id' => $child->id,
            'date' => $date,
        ]);
    }

    public function test_staff_can_adjust_any_child(): void
    {
        $date = $this->upcomingWeekday();
        $child = Child::factory()->create();

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => $date,
                'planned_time' => '15:00',
                'planned_method' => DepartureMethod::PickedUp->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => $date,
            'planned_time' => '15:00',
        ]);
    }

    public function test_a_day_in_a_future_week_can_be_adjusted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24')); // Wednesday
        $child = Child::factory()->create();

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $child->id,
                'date' => '2026-07-01', // a Wednesday next week
                'planned_time' => '15:00',
                'planned_method' => DepartureMethod::PickedUp->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => '2026-07-01',
            'planned_time' => '15:00',
        ]);
    }
}
