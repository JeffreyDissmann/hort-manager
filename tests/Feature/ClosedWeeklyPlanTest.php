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
