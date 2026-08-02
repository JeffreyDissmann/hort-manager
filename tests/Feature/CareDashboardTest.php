<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\HomeworkDefault;
use App\Models\WeeklySchedule;
use App\Services\HortDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The TRMNL staff-room feed on a Ferienbetreuung day: the Hort is open, but the
 * roster comes from the sign-ups — the Stammplan says nothing during the holidays.
 */
class CareDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Child $mia;

    private Child $ben;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday

        $this->mia = Child::factory()->create(['name' => 'Mia']);
        $this->ben = Child::factory()->create(['name' => 'Ben']);

        foreach ([$this->mia, $this->ben] as $child) {
            foreach ([1, 2, 3, 4, 5] as $weekday) {
                WeeklySchedule::create([
                    'child_id' => $child->id,
                    'weekday' => $weekday,
                    'planned_time' => '15:00',
                    'method' => DepartureMethod::PickedUp,
                ]);
            }
        }

        // Ferienbetreuung Mon–Tue of this week; Wed–Fri stay normal Hort days.
        $period = HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-04',
        ]);
        $period->generateCareDays();
    }

    private function signUp(Child $child, string $date): DailyDeparture
    {
        $day = HolidayCareDay::firstWhere('date', $date);

        return DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $date,
            'holiday_care_day_id' => $day->id,
            'planned_time' => $day->ends_at,
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);
    }

    public function test_todays_roster_is_the_sign_ups_not_the_stammplan(): void
    {
        $this->signUp($this->mia, '2026-08-03');

        $today = app(HortDashboardData::class)->build()['today'];

        $this->assertSame('Sommer-Ferienbetreuung', $today['care']);
        $this->assertSame(1, $today['present_count']);
        $this->assertSame('16:30', $today['next_pickup']);
        $this->assertSame('16:30', $today['departures'][0]['time']);
        $this->assertSame([['name' => 'Mia', 'alone' => false, 'left' => false, 'excursion' => false, 'deviation' => null]], $today['departures'][0]['children']);
    }

    public function test_a_plain_override_on_that_date_is_not_a_sign_up(): void
    {
        // Someone planned a pickup before the Ferienbetreuung existed. That is not a
        // registration, and putting them on the roster would say a child is there.
        DailyDeparture::create([
            'child_id' => $this->ben->id,
            'date' => '2026-08-03',
            'planned_time' => '14:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $data = app(HortDashboardData::class)->build();

        $this->assertSame(0, $data['today']['present_count']);
        $this->assertSame([], $data['today']['departures']);
        $this->assertSame([], $data['week'][0]['departures']);
    }

    public function test_a_registered_child_reported_sick_is_off_the_board(): void
    {
        $this->signUp($this->mia, '2026-08-03');
        Absence::report($this->mia, '2026-08-03', AbsenceReason::Sick, null);

        $today = app(HortDashboardData::class)->build()['today'];

        $this->assertSame(0, $today['present_count']);
        $this->assertSame('Mia', $today['absent'][0]['name']);
    }

    public function test_the_week_mixes_care_days_and_normal_days(): void
    {
        $this->signUp($this->mia, '2026-08-04');

        $week = app(HortDashboardData::class)->build()['week'];

        // Tuesday: care day — only the sign-up, at the Betreuungszeit.
        $this->assertSame('Sommer-Ferienbetreuung', $week[1]['care']);
        $this->assertSame('16:30', $week[1]['departures'][0]['time']);
        $this->assertSame(['Mia'], $week[1]['departures'][0]['names']);

        // Wednesday: an ordinary day again — both children on their Stammplan.
        $this->assertNull($week[2]['care']);
        $this->assertSame(['Ben', 'Mia'], $week[2]['departures'][0]['names']);
    }

    public function test_no_homework_band_during_the_holidays(): void
    {
        HomeworkDefault::create(['weekday' => 1, 'starts_at' => '14:00', 'ends_at' => '15:00']);

        $program = app(HortDashboardData::class)->build()['today']['program'];

        // No school, so the per-weekday default must not leak in — the Betreuungszeit
        // takes its place, exactly as on /program.
        $this->assertNull($program['homework']);
        $this->assertSame('08:30–16:30', $program['care_time']);
    }
}
