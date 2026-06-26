<?php

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WeeklyOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('weekly-plan'))->assertRedirect(route('login'));
    }

    public function test_timetable_places_each_child_in_their_time_slot(): void
    {
        $emma = Child::factory()->create(['name' => 'Emma']);
        $emma->weeklySchedules()->create([
            'weekday' => 1, // Montag
            'planned_time' => '14:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        $mia = Child::factory()->create(['name' => 'Mia']);
        $mia->weeklySchedules()->create([
            'weekday' => 1, // Montag
            'planned_time' => '15:00',
            'method' => DepartureMethod::SentHome,
        ]);

        // A parent (open information policy) sees every child, not just their own.
        $parent = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($parent)
            ->get(route('weekly-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WeeklyPlan/Index')
                // Slots run 14:00, 14:30, 15:00 → three rows.
                ->has('rows', 3)
                ->where('rows.0.time', '14:00')
                ->where('rows.0.days.0.0.name', 'Emma')
                ->where('rows.2.time', '15:00')
                ->where('rows.2.days.0.0.name', 'Mia')
                // Nobody leaves Tuesday → empty cell.
                ->where('rows.0.days.1', [])
            );
    }
}
