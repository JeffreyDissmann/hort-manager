<?php

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
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

        // The standard timetable shows every child (open information policy),
        // but "Diese Woche" is scoped to the parent's own children.
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $parent->children()->attach($emma);

        $this->actingAs($parent)
            ->get(route('weekly-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WeeklyPlan/Index')
                // Only Emma (their own child) in the editable current week.
                ->has('currentWeek', 1)
                ->where('currentWeek.0.name', 'Emma')
                // Standard timetable still lists everyone: slots 14:00, 14:30, 15:00.
                ->has('standard', 3)
                ->where('standard.0.time', '14:00')
                ->where('standard.0.days.0.0.name', 'Emma')
                ->where('standard.2.time', '15:00')
                ->where('standard.2.days.0.0.name', 'Mia')
                ->where('standard.0.days.1', [])
            );
    }

    public function test_the_week_parameter_navigates_to_another_week(): void
    {
        $this->travelTo(Carbon::parse('2026-06-24')); // current week starts Mo 2026-06-22

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('weekly-plan', ['week' => '2026-06-29'])) // next week
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.is_current', false)
                ->where('weekDays.0.date', '2026-06-29') // Monday
                ->where('weekDays.4.date', '2026-07-03') // Friday
                ->where('week.prev', '2026-06-22')
                ->where('week.next', '2026-07-06')
            );
    }

    public function test_current_week_flags_an_excursion_pickup_conflict(): void
    {
        $this->travelTo(Carbon::parse('2026-06-24')); // Wednesday (week day index 2)

        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 3, // Mittwoch
            'planned_time' => '14:30',
            'method' => DepartureMethod::PickedUp,
        ]);

        $excursion = Excursion::factory()->create([
            'date' => '2026-06-24',
            'depart_at' => '13:30',
            'return_at' => '15:30', // pickup 14:30 falls inside → conflict
        ]);
        $excursion->children()->attach($child->id, ['response' => true]);

        $this->actingAs($parent)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentWeek.0.days.2.excursion.name', $excursion->name)
                ->where('currentWeek.0.days.2.conflict', true)
                ->where('activities.2.0.name', $excursion->name)
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

        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $parent->children()->attach($child);

        $this->actingAs($parent)
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
