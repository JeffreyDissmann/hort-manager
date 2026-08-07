<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The Wochenplan on a Ferienbetreuung week: the Stammplan stops applying. */
class CareWeeklyPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Child $child;

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

    /** Ferienbetreuung Mon–Wed of the shown week. */
    private function careDays(): HolidayPeriod
    {
        $period = HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-05',
        ]);
        $period->generateCareDays();

        return $period;
    }

    private function signUp(string $date): DailyDeparture
    {
        $day = HolidayCareDay::firstWhere('date', $date);

        return DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => $date,
            'holiday_care_day_id' => $day->id,
            'planned_time' => $day->ends_at,
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);
    }

    public function test_an_unregistered_care_day_is_flagged_and_locked(): void
    {
        $this->careDays();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('careDays', [
                    '2026-08-03' => 'Sommer-Ferienbetreuung',
                    '2026-08-04' => 'Sommer-Ferienbetreuung',
                    '2026-08-05' => 'Sommer-Ferienbetreuung',
                ])
                ->where('currentWeek.0.days.0.care.registered', false)
                // The Stammplan's 15:00 must not leak into a holiday.
                ->where('currentWeek.0.days.0.time', null)
                ->where('currentWeek.0.days.0.editable', false)
            );
    }

    public function test_a_plan_predating_the_period_does_not_make_the_day_editable(): void
    {
        // The row exists but names no care day, so it is not a sign-up: the cell used
        // to open an editor that saved something nobody could see.
        $this->careDays();
        DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-08-03',
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentWeek.0.days.0.care.registered', false)
                ->where('currentWeek.0.days.0.editable', false)
            );
    }

    public function test_an_unregistered_care_day_says_whether_signing_up_is_still_open(): void
    {
        // While the Anmeldung runs the cell offers the way to it; afterwards it can
        // only explain itself, so the deadline travels with the day.
        $period = $this->careDays();
        $period->update(['registration_deadline' => '2026-08-01']);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentWeek.0.days.0.care.open', false)
                ->where('currentWeek.0.days.0.care.deadline', '2026-08-01')
            );
    }

    public function test_a_registered_care_day_is_planned_as_usual(): void
    {
        $this->careDays();
        $this->signUp('2026-08-03');

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentWeek.0.days.0.care.registered', true)
                ->where('currentWeek.0.days.0.time', '16:00')
                ->where('currentWeek.0.days.0.editable', true)
                // Nothing to deviate from, so it isn't „heute geändert".
                ->where('currentWeek.0.days.0.adjusted', false)
            );
    }

    public function test_the_stammplan_comment_does_not_travel_into_a_care_day(): void
    {
        // „früher wegen Schwimmkurs" next to the Betreuungszeit contradicts the very
        // time it is printed under — the Stammplan says nothing during the holidays.
        $this->child->weeklySchedules()->where('weekday', 1)->update(['comment' => 'früher wegen Schwimmkurs']);
        $this->careDays();
        $this->signUp('2026-08-03');

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentWeek.0.days.0.comment', null)
                // …and the editor doesn't pre-fill it either.
                ->where('currentWeek.0.days.0.note', null)
                // Thursday is an ordinary day again, where it belongs.
                ->where('currentWeek.0.days.3.comment', null)
            );
    }

    public function test_an_unregistered_plan_stays_off_the_whole_week_timeline(): void
    {
        $this->careDays();
        DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-08-03',
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $timetable = $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->viewData('page')['props']['weekTimetable'];

        $monday = collect($timetable)->flatMap(fn (array $row): array => collect($row['days'][0])->all());

        $this->assertCount(0, $monday);
    }

    public function test_the_normal_days_of_the_week_are_untouched(): void
    {
        $this->careDays();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                // Thursday is a normal Hort day again.
                ->where('currentWeek.0.days.3.care', null)
                ->where('currentWeek.0.days.3.time', '15:00')
                ->where('currentWeek.0.days.3.editable', true)
            );
    }

    public function test_nobody_is_hortfrei_on_a_care_day(): void
    {
        // A child with no Wednesday would normally be listed as „hortfrei".
        $hortfreiChild = Child::factory()->create(['name' => 'Ben']);
        WeeklySchedule::create([
            'child_id' => $hortfreiChild->id,
            'weekday' => 1,
            'planned_time' => '15:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        $this->careDays();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->count('weekHortfrei.1', 0)  // Tuesday: care day
                ->count('weekHortfrei.3', 1)  // Thursday: normal, Ben is hortfrei
            );
    }

    public function test_the_timetable_only_lists_children_who_signed_up(): void
    {
        $this->careDays();
        $this->signUp('2026-08-03');

        $timetable = $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->viewData('page')['props']['weekTimetable'];

        // Monday holds only the sign-up (16:00); Tuesday nobody signed up for.
        $monday = collect($timetable)->flatMap(fn (array $row): array => collect($row['days'][0])->all());
        $tuesday = collect($timetable)->flatMap(fn (array $row): array => collect($row['days'][1])->all());

        $this->assertCount(1, $monday);
        $this->assertSame('Mia', $monday->first()['name']);
        $this->assertCount(0, $tuesday);
    }

    public function test_the_timetable_keeps_the_stammplan_on_normal_days(): void
    {
        $this->careDays();

        $timetable = $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->viewData('page')['props']['weekTimetable'];

        $thursday = collect($timetable)->flatMap(fn (array $row): array => collect($row['days'][3])->all());

        $this->assertCount(1, $thursday);
        $this->assertSame('Mia', $thursday->first()['name']);
    }

    public function test_the_companion_picker_only_offers_children_who_are_there(): void
    {
        // Ben has the same full Stammplan but didn't sign up for the Ferienbetreuung.
        $ben = Child::factory()->create(['name' => 'Ben']);
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WeeklySchedule::create([
                'child_id' => $ben->id,
                'weekday' => $weekday,
                'planned_time' => '15:00',
                'method' => DepartureMethod::PickedUp,
            ]);
        }

        $this->careDays();
        $this->signUp('2026-08-03');

        $children = collect($this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->viewData('page')['props']['children'])->keyBy('name');

        // Monday is a care day: only Mia can be a companion, and at her sign-up time.
        $this->assertSame('16:00', $children['Mia']['times']['2026-08-03']);
        $this->assertArrayNotHasKey('2026-08-03', $children['Ben']['times']);

        // Thursday is an ordinary day again — the Stammplan applies to both.
        $this->assertSame('15:00', $children['Ben']['times']['2026-08-06']);
    }

    public function test_a_week_without_care_is_unchanged(): void
    {
        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('careDays', [])
                ->where('currentWeek.0.days.0.care', null)
                ->where('currentWeek.0.days.0.time', '15:00')
            );
    }
}
