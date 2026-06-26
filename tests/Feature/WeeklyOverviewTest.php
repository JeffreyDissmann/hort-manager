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

class WeeklyOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('weekly-plan'))->assertRedirect(route('login'));
    }

    public function test_standard_timetable_places_each_child_in_their_time_slot(): void
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
                ->has('currentWeek', 2)
                // Standard timetable slots run 14:00, 14:30, 15:00 → three rows.
                ->has('standard', 3)
                ->where('standard.0.time', '14:00')
                ->where('standard.0.days.0.0.name', 'Emma')
                ->where('standard.2.time', '15:00')
                ->where('standard.2.days.0.0.name', 'Mia')
                ->where('standard.0.days.1', [])
            );
    }

    public function test_current_week_reflects_a_same_day_override(): void
    {
        $this->travelTo(Carbon::parse('2026-06-24')); // a Wednesday

        $child = Child::factory()->create(['name' => 'Emma']);
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 3, // Mittwoch
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        // An override for this Wednesday: earlier pickup.
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-24',
            'planned_time' => '14:30',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Parent]))
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                // Wednesday is index 2 (Mo, Di, Mi).
                ->where('currentWeek.0.days.2.time', '14:30')
                ->where('currentWeek.0.days.2.adjusted', true)
                // Monday has no override → falls back to standard (no schedule → frei).
                ->where('currentWeek.0.days.0.time', null)
            );
    }
}
