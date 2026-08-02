<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The Wochenplan locks Schließzeiten and drops them from every „nicht da" list. */
class ClosedWeeklyPlanTest extends TestCase
{
    use RefreshDatabase;

    private Child $child;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->child = Child::factory()->create(['name' => 'Mia']);
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WeeklySchedule::create([
                'child_id' => $this->child->id,
                'weekday' => $weekday,
                'planned_time' => '15:00',
                'method' => DepartureMethod::PickedUp,
            ]);
        }
    }

    /** Closes Wednesday of the shown week. */
    private function closeWednesday(): HolidayPeriod
    {
        return HolidayPeriod::factory()->onDay('2026-08-05')->create(['name' => 'Fortbildung']);
    }

    public function test_a_closed_day_is_flagged_and_locked(): void
    {
        $this->closeWednesday();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('closedDays', ['2026-08-05' => 'Fortbildung'])
                // Mo–Fr: only Wednesday is closed, and only Wednesday is locked.
                ->where('currentWeek.0.days.2.closed', 'Fortbildung')
                ->where('currentWeek.0.days.2.editable', false)
                ->where('currentWeek.0.days.1.closed', null)
                ->where('currentWeek.0.days.1.editable', true)
            );
    }

    public function test_nobody_is_hortfrei_or_absent_on_a_closed_day(): void
    {
        // Reported away before the closure was entered, and a child with no Wednesday.
        Absence::report($this->child, '2026-08-05', AbsenceReason::Sick, $this->staff->id);
        $hortfreiChild = Child::factory()->create(['name' => 'Ben']);
        WeeklySchedule::create([
            'child_id' => $hortfreiChild->id,
            'weekday' => 1,
            'planned_time' => '15:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        $this->closeWednesday();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->count('weekAbsences.2', 0)
                ->count('weekHortfrei.2', 0)
                // Tuesday still lists Ben as „hortfrei" — only the closed day is emptied.
                ->count('weekHortfrei.1', 1)
            );
    }

    public function test_the_timetable_skips_a_closed_day(): void
    {
        $this->closeWednesday();

        $timetable = $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->viewData('page')['props']['weekTimetable'];

        // Each row is { time, days: [Mo…Fr] }, every day a list of pickups.
        foreach ($timetable as $row) {
            $this->assertEmpty(collect($row['days'][2])->all(), "Wednesday not empty at {$row['time']}");
        }

        $fifteen = collect($timetable)->firstWhere('time', '15:00');
        $this->assertNotEmpty(collect($fifteen['days'][1])->all());
    }

    public function test_planning_a_closed_day_is_refused(): void
    {
        $this->closeWednesday();

        $this->actingAs($this->staff)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-05',
                'planned_time' => '16:00',
                'planned_method' => 'picked_up',
            ])
            ->assertForbidden();

        $this->assertDatabaseEmpty('daily_departures');
    }

    public function test_resetting_a_closed_day_is_refused(): void
    {
        $this->closeWednesday();

        $this->actingAs($this->staff)
            ->patch(route('weekly-plan.reset'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-05',
            ])
            ->assertForbidden();
    }

    public function test_reporting_an_absence_skips_closed_days_but_keeps_the_rest(): void
    {
        $this->closeWednesday();

        $this->actingAs($this->staff)
            ->post(route('absences.store'), [
                'child_id' => $this->child->id,
                'from' => '2026-08-04',
                'to' => '2026-08-06',
                'reason' => 'sick',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('absences', ['date' => '2026-08-04']);
        $this->assertDatabaseHas('absences', ['date' => '2026-08-06']);
        $this->assertDatabaseMissing('absences', ['date' => '2026-08-05']);
    }

    public function test_a_fully_closed_week_locks_every_day(): void
    {
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-07')->create(['name' => 'Sommerferien']);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(function (Assert $page) {
                $props = $page->toArray()['props'];

                $this->assertCount(5, $props['closedDays']);

                foreach ($props['currentWeek'][0]['days'] as $day) {
                    $this->assertSame('Sommerferien', $day['closed']);
                    $this->assertFalse($day['editable']);
                }

                // Nothing left to place on the timeline.
                foreach ($props['weekTimetable'] as $row) {
                    foreach ($row['days'] as $entries) {
                        $this->assertEmpty(collect($entries)->all());
                    }
                }
            });
    }

    public function test_a_closure_from_the_previous_week_only_locks_the_days_it_covers(): void
    {
        // Runs Mon 27 Jul – Tue 4 Aug: only Mo+Tue of the shown week.
        HolidayPeriod::factory()->between('2026-07-27', '2026-08-04')->create(['name' => 'Ferien']);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('closedDays', ['2026-08-03' => 'Ferien', '2026-08-04' => 'Ferien'])
                ->where('currentWeek.0.days.0.closed', 'Ferien')
                ->where('currentWeek.0.days.1.closed', 'Ferien')
                ->where('currentWeek.0.days.2.closed', null)
                ->where('currentWeek.0.days.2.editable', true)
            );
    }

    public function test_a_closure_running_past_friday_only_locks_the_days_it_covers(): void
    {
        HolidayPeriod::factory()->between('2026-08-06', '2026-08-21')->create(['name' => 'Ferien']);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('closedDays', ['2026-08-06' => 'Ferien', '2026-08-07' => 'Ferien'])
                ->where('currentWeek.0.days.4.closed', 'Ferien')
                ->where('currentWeek.0.days.3.closed', 'Ferien')
                ->where('currentWeek.0.days.2.closed', null)
            );
    }

    public function test_two_separate_closures_in_one_week_are_both_shown(): void
    {
        HolidayPeriod::factory()->onDay('2026-08-03')->create(['name' => 'Brückentag']);
        HolidayPeriod::factory()->onDay('2026-08-07')->create(['name' => 'Fortbildung']);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('closedDays', ['2026-08-03' => 'Brückentag', '2026-08-07' => 'Fortbildung'])
                ->where('currentWeek.0.days.0.closed', 'Brückentag')
                ->where('currentWeek.0.days.4.closed', 'Fortbildung')
                ->where('currentWeek.0.days.2.closed', null)
            );
    }

    public function test_a_weekend_only_closure_locks_nothing(): void
    {
        HolidayPeriod::factory()->between('2026-08-08', '2026-08-09')->create();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page->where('closedDays', []));
    }

    public function test_a_closure_ending_the_day_before_the_week_locks_nothing(): void
    {
        HolidayPeriod::factory()->between('2026-07-20', '2026-08-02')->create();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page->where('closedDays', []));
    }

    public function test_an_open_week_is_unchanged(): void
    {
        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('closedDays', [])
                ->where('currentWeek.0.days.2.closed', null)
                ->where('currentWeek.0.days.2.editable', true)
            );
    }
}
