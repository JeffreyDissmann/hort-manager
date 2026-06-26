<?php

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\Excursion;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExcursionManagementTest extends TestCase
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

    public function test_staff_can_plan_an_excursion_with_children(): void
    {
        $a = Child::factory()->create();
        $b = Child::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('excursions.store'), [
                'name' => 'Zoo-Ausflug',
                'date' => '2026-06-29',
                'depart_at' => '09:00',
                'return_at' => '15:00',
                'children' => [$a->id, $b->id],
            ])
            ->assertRedirect(route('excursions.index'));

        $excursion = Excursion::firstWhere('name', 'Zoo-Ausflug');
        $this->assertNotNull($excursion);
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $excursion->children()->pluck('children.id')->all(),
        );
    }

    public function test_planning_requires_a_name_and_date(): void
    {
        $this->actingAs($this->staff())
            ->post(route('excursions.store'), ['name' => '', 'date' => ''])
            ->assertSessionHasErrors(['name', 'date']);
    }

    public function test_parents_cannot_manage_excursions(): void
    {
        $parent = $this->parent();

        $this->actingAs($parent)->get(route('excursions.index'))->assertForbidden();
        $this->actingAs($parent)->get(route('excursions.create'))->assertForbidden();
        $this->actingAs($parent)
            ->post(route('excursions.store'), ['name' => 'X', 'date' => '2026-06-29'])
            ->assertForbidden();
    }

    public function test_staff_can_update_the_participating_children(): void
    {
        $excursion = Excursion::factory()->create();
        $a = Child::factory()->create();
        $b = Child::factory()->create();
        $excursion->children()->attach($a);

        $this->actingAs($this->staff())
            ->put(route('excursions.update', $excursion), [
                'name' => $excursion->name,
                'date' => $excursion->date->toDateString(),
                'children' => [$b->id], // swap a -> b
            ])
            ->assertRedirect(route('excursions.index'));

        $this->assertEqualsCanonicalizing(
            [$b->id],
            $excursion->children()->pluck('children.id')->all(),
        );
    }

    public function test_deleting_an_excursion_removes_its_links(): void
    {
        $excursion = Excursion::factory()->create();
        $child = Child::factory()->create();
        $excursion->children()->attach($child);

        $this->actingAs($this->staff())
            ->delete(route('excursions.destroy', $excursion))
            ->assertRedirect(route('excursions.index'));

        $this->assertDatabaseMissing('excursions', ['id' => $excursion->id]);
        $this->assertDatabaseMissing('child_excursion', ['excursion_id' => $excursion->id]);
    }

    public function test_staff_can_flip_the_live_trip_state(): void
    {
        $staff = $this->staff();
        $excursion = Excursion::factory()->create();
        $this->assertSame('planned', $excursion->state());

        $this->actingAs($staff)
            ->patch(route('excursions.live', $excursion), ['event' => 'depart'])
            ->assertRedirect();
        $this->assertSame('away', $excursion->refresh()->state());
        $this->assertNotNull($excursion->departed_at);

        $this->actingAs($staff)
            ->patch(route('excursions.live', $excursion), ['event' => 'return']);
        $this->assertSame('back', $excursion->refresh()->state());

        // Undo the departure clears both timestamps.
        $this->actingAs($staff)
            ->patch(route('excursions.live', $excursion), ['event' => 'undo_depart']);
        $excursion->refresh();
        $this->assertSame('planned', $excursion->state());
        $this->assertNull($excursion->returned_at);
    }

    public function test_parents_cannot_flip_the_live_state(): void
    {
        $excursion = Excursion::factory()->create();

        $this->actingAs($this->parent())
            ->patch(route('excursions.live', $excursion), ['event' => 'depart'])
            ->assertForbidden();
    }

    public function test_board_reflects_the_live_excursion_state(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = Child::factory()->create();
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 1,
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp,
        ]);
        $excursion = Excursion::factory()->create([
            'date' => '2026-06-22',
            'departed_at' => now(),
        ]);
        $excursion->children()->attach($child);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('excursions.0.state', 'away')
                ->where('rows.0.excursion.state', 'away')
            );
    }

    public function test_excursion_shows_as_an_overlay_on_the_board(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday

        $child = Child::factory()->create();
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 1,
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        $excursion = Excursion::factory()->create([
            'name' => 'Waldtag',
            'date' => '2026-06-22',
            'return_at' => '15:30',
        ]);
        $excursion->children()->attach($child);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.excursion.name', 'Waldtag')
                ->where('rows.0.excursion.return_at', '15:30')
                // The child still gets picked up normally after returning.
                ->where('rows.0.status', 'present')
            );
    }
}
