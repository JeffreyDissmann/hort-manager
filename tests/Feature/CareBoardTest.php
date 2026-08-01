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
use App\Models\HomeworkDefault;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * „Heute" on a Ferienbetreuung day: the roster is who signed up, not the Stammplan.
 * Everything else — marking off, Krankmeldung, the day editor — works as usual.
 */
class CareBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Child $signedUp;

    private Child $notSignedUp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);

        HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

        // Both have a normal Monday Stammplan — during the holidays that says nothing.
        $this->signedUp = Child::factory()->create(['name' => 'Mia']);
        $this->notSignedUp = Child::factory()->create(['name' => 'Ben']);

        foreach ([$this->signedUp, $this->notSignedUp] as $child) {
            WeeklySchedule::create([
                'child_id' => $child->id,
                'weekday' => 1,
                'planned_time' => '15:00',
                'method' => DepartureMethod::PickedUp,
            ]);
        }
    }

    private function careToday(): HolidayCareDay
    {
        $period = HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
        ]);
        $period->generateCareDays();

        return $period->careDays()->firstWhere('date', '2026-08-03');
    }

    /** Sign a child up the way the registration screen does. */
    private function signUp(Child $child, HolidayCareDay $day): DailyDeparture
    {
        return DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $day->date->toDateString(),
            'planned_time' => $day->ends_at,
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);
    }

    public function test_only_signed_up_children_are_on_the_board(): void
    {
        $day = $this->careToday();
        $this->signUp($this->signedUp, $day);

        $this->actingAs($this->staff)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('care.name', 'Sommer-Ferienbetreuung')
                ->where('care.starts_at', '08:30')
                ->where('care.ends_at', '16:30')
                ->has('rows', 1)
                ->where('rows.0.name', 'Mia')
            );
    }

    public function test_it_seeds_nobody_from_the_stammplan(): void
    {
        $this->careToday();

        $this->actingAs($this->staff)->get(route('board'))->assertOk();

        // A normal Monday would have created a row for both children.
        $this->assertDatabaseEmpty('daily_departures');
    }

    public function test_nobody_is_hortfrei_on_a_care_day(): void
    {
        $day = $this->careToday();
        $this->signUp($this->signedUp, $day);

        $this->actingAs($this->staff)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->count('hortfrei', 0));
    }

    public function test_no_homework_is_shown_on_a_care_day(): void
    {
        $day = $this->careToday();
        $this->signUp($this->signedUp, $day);

        $this->actingAs($this->staff)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->where('program', null));
    }

    public function test_staff_can_still_mark_a_child_off(): void
    {
        $day = $this->careToday();
        $departure = $this->signUp($this->signedUp, $day);

        $this->actingAs($this->staff)
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::PickedUp->value])
            ->assertRedirect();

        $this->assertNotNull($departure->fresh()->left_at);
    }

    public function test_a_normal_day_is_unaffected(): void
    {
        // The Ferienbetreuung is next week.
        $period = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-14',
        ]);
        $period->generateCareDays();

        $this->actingAs($this->staff)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('care', null)
                ->has('rows', 2)
            );

        $this->assertDatabaseCount('daily_departures', 2);
    }

    public function test_a_closure_still_wins_over_a_care_day(): void
    {
        // Both on the same date — the Hort being shut is the stronger statement.
        $this->careToday();
        HolidayPeriod::factory()->onDay('2026-08-03')->create(['name' => 'Brückentag']);

        $this->actingAs($this->staff)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('closure.name', 'Brückentag')
                ->missing('care')
            );
    }
}
