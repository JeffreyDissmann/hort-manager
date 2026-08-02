<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\WeeklySchedule;
use App\Services\HortDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The TRMNL staff-room feed builds its own view of the day — it has to ask too. */
class ClosedDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday

        $child = Child::factory()->create(['name' => 'Mia']);
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WeeklySchedule::create([
                'child_id' => $child->id,
                'weekday' => $weekday,
                'planned_time' => '15:00',
                'method' => DepartureMethod::PickedUp,
            ]);
        }
    }

    public function test_it_reports_a_closed_day_instead_of_an_empty_board(): void
    {
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-07')->create(['name' => 'Sommerferien']);

        $data = app(HortDashboardData::class)->build();

        $this->assertSame('Sommerferien', $data['today']['closed']);
        $this->assertSame(0, $data['today']['present_count']);
        $this->assertSame([], $data['today']['departures']);
        $this->assertNull($data['today']['program']);

        foreach ($data['week'] as $day) {
            $this->assertSame('Sommerferien', $day['closed']);
            $this->assertSame([], $day['departures']);
        }
    }

    public function test_it_keeps_the_open_days_of_a_partly_closed_week(): void
    {
        HolidayPeriod::factory()->onDay('2026-08-05')->create(['name' => 'Fortbildung']);

        $data = app(HortDashboardData::class)->build();

        // Today (Monday) is open and still lists the pickup.
        $this->assertArrayNotHasKey('closed', array_filter($data['today'], fn ($v) => $v !== null));
        $this->assertSame(1, $data['today']['present_count']);

        $this->assertNull($data['week'][0]['closed']);
        $this->assertSame('Fortbildung', $data['week'][2]['closed']);
        $this->assertNotSame([], $data['week'][3]['departures']);
    }
}
