<?php

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChildManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_manage_children(): void
    {
        $this->get(route('children.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_children(): void
    {
        $child = Child::factory()->create(['name' => 'Emma']);

        $this->actingAs(User::factory()->create())
            ->get(route('children.index'))
            ->assertOk();

        $this->assertDatabaseHas('children', ['name' => 'Emma', 'id' => $child->id]);
    }

    public function test_a_child_can_be_created(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('children.store'), [
                'name' => 'Liam',
                'date_of_birth' => '2019-04-01',
                'note' => 'Erdnussallergie',
            ]);

        $child = Child::firstWhere('name', 'Liam');

        $this->assertNotNull($child);
        $response->assertRedirect(route('children.edit', $child));
        $this->assertSame('Erdnussallergie', $child->note);
    }

    public function test_creating_a_child_requires_a_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('children.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('children', 0);
    }

    public function test_updating_a_child_upserts_the_weekly_schedule(): void
    {
        $child = Child::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('children.update', $child), [
                'name' => 'Mia',
                'schedule' => [
                    ['weekday' => 1, 'planned_time' => '16:00', 'method' => DepartureMethod::PickedUp->value],
                    ['weekday' => 2, 'planned_time' => '', 'method' => null],
                    ['weekday' => 5, 'planned_time' => '14:30', 'method' => DepartureMethod::SentHome->value],
                ],
            ])
            ->assertRedirect(route('children.index'));

        $this->assertSame('Mia', $child->refresh()->name);

        // Days with a time are stored; the empty day is not.
        $this->assertDatabaseHas('weekly_schedules', [
            'child_id' => $child->id,
            'weekday' => 1,
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp->value,
        ]);
        $this->assertDatabaseMissing('weekly_schedules', [
            'child_id' => $child->id,
            'weekday' => 2,
        ]);
        $this->assertSame(2, $child->weeklySchedules()->count());
    }

    public function test_clearing_a_weekday_time_removes_that_schedule(): void
    {
        $child = Child::factory()->create();
        $child->weeklySchedules()->create([
            'weekday' => 3,
            'planned_time' => '15:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        $this->actingAs(User::factory()->create())
            ->patch(route('children.update', $child), [
                'name' => $child->name,
                'schedule' => [
                    ['weekday' => 3, 'planned_time' => '', 'method' => null],
                ],
            ]);

        $this->assertDatabaseMissing('weekly_schedules', [
            'child_id' => $child->id,
            'weekday' => 3,
        ]);
    }

    public function test_a_child_can_be_deleted(): void
    {
        $child = Child::factory()->create();
        $child->weeklySchedules()->create([
            'weekday' => 1,
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        $this->actingAs(User::factory()->create())
            ->delete(route('children.destroy', $child))
            ->assertRedirect(route('children.index'));

        $this->assertDatabaseMissing('children', ['id' => $child->id]);
        // Schedules cascade with the child.
        $this->assertDatabaseMissing('weekly_schedules', ['child_id' => $child->id]);
    }
}
