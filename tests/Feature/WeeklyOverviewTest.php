<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
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

    public function test_this_week_is_scoped_to_the_parents_own_children(): void
    {
        Carbon::setTestNow('2026-07-06'); // Monday
        $emma = Child::factory()->create(['name' => 'Emma']);
        $emma->weeklySchedules()->create(['weekday' => 1, 'planned_time' => '14:00', 'method' => DepartureMethod::PickedUp]);
        Child::factory()->create(['name' => 'Mia']); // someone else's child

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
                // The week payload now carries relative offset + today flags.
                ->where('week.offset', 0)
                ->where('week.is_current', true)
                ->where('weekDays.0.is_today', true) // Monday
                // The standard timetable moved to its own page.
                ->missing('standard')
            );
    }

    public function test_week_hortfrei_lists_children_not_at_the_hort_per_weekday(): void
    {
        Carbon::setTestNow('2026-07-06'); // Monday (weekday 1)

        // Comes Tuesdays only → „Hortfrei" on Monday, but has a plan.
        $tuesday = Child::factory()->create(['name' => 'Tuesday Kid']);
        $tuesday->weeklySchedules()->create(['weekday' => 2, 'planned_time' => '15:00', 'method' => DepartureMethod::PickedUp]);

        // Comes Mondays → present Monday, not in the Hortfrei list.
        $monday = Child::factory()->create(['name' => 'Monday Kid']);
        $monday->weeklySchedules()->create(['weekday' => 1, 'planned_time' => '15:00', 'method' => DepartureMethod::PickedUp]);

        // No Stammplan at all → not „Hortfrei" (that's the „no plan" case).
        Child::factory()->create(['name' => 'Unplanned Kid']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('weekHortfrei.0', ['Tuesday Kid'])  // Monday column
                ->where('weekHortfrei.1', ['Monday Kid'])    // Tuesday column
            );
    }

    public function test_the_standard_plan_page_lists_every_child(): void
    {
        $emma = Child::factory()->create(['name' => 'Emma']);
        $emma->weeklySchedules()->create(['weekday' => 1, 'planned_time' => '14:00', 'method' => DepartureMethod::PickedUp]);

        $mia = Child::factory()->create(['name' => 'Mia']);
        $mia->weeklySchedules()->create(['weekday' => 1, 'planned_time' => '15:00', 'method' => DepartureMethod::SentHome]);

        // Open information: a parent sees every child's standard plan, not just their own.
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $parent->children()->attach($emma);

        $this->actingAs($parent)
            ->get(route('standard-plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StandardPlan/Index')
                ->has('standard', 3) // slots 14:00, 14:30, 15:00
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

    public function test_current_week_flags_a_birthday(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // week Mo 06-22 … Fr 06-26
        $child = Child::factory()->create(['date_of_birth' => '2018-06-24']); // Wednesday → index 2

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentWeek.0.days.2.birthday', 8)
                ->where('currentWeek.0.days.0.birthday', null)
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

    public function test_the_week_timetable_shows_all_children_with_effective_times(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday = today (editable)

        $child = Child::factory()->create(['name' => 'Emma']);
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 1, // Montag
            'planned_time' => '14:00',
            'method' => DepartureMethod::PickedUp,
        ]);
        // Override this Monday to a later pickup.
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        // Staff see the whole-week timetable (all children), with the effective time.
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('weekTimetable', 1)
                ->where('weekTimetable.0.time', '15:00')
                ->where('weekTimetable.0.days.0.0.name', 'Emma')
                ->where('weekTimetable.0.days.0.0.time', '15:00')
                ->where('weekTimetable.0.days.0.0.adjusted', true)
                ->where('weekTimetable.0.days.0.0.editable', true) // today, staff can edit
                ->where('weekTimetable.0.days.1', []) // Tuesday empty
            );
    }

    public function test_an_absent_child_is_dropped_from_the_week_timetable(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday

        $child = Child::factory()->create(['name' => 'Emma']);
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 1, // Montag
            'planned_time' => '14:00',
            'method' => DepartureMethod::PickedUp,
        ]);
        // Reported away on Monday → must not fall back to the Stammplan on the grid.
        Absence::create(['child_id' => $child->id, 'date' => '2026-06-22', 'reason' => 'away']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('weekTimetable', []) // no rows: Emma's only day is her absence
                // …but she is listed in the not-coming summary for Monday (index 0).
                ->where('weekAbsences.0.0.name', 'Emma')
                ->where('weekAbsences.0.0.label', 'Kommt nicht')
                ->where('weekAbsences.1', []) // Tuesday: nobody away
            );
    }

    public function test_the_week_timetable_spans_the_homework_window(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday

        $child = Child::factory()->create();
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 1, // Montag, leaves at 16:00
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp,
        ]);
        // Homework 14:00–15:00 on Mondays → the timetable must start at 14:00.
        HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                // 14:00, 14:30, 15:00, 15:30, 16:00 → five rows.
                ->has('weekTimetable', 5)
                ->where('weekTimetable.0.time', '14:00')
                ->where('weekTimetable.4.time', '16:00')
                ->where('weekTimetable.4.days.0.0.id', $child->id)
            );
    }
}
