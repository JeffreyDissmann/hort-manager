<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TrmnlDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function scheduleFor(Child $child, int $weekday, string $time, DepartureMethod $method = DepartureMethod::PickedUp): void
    {
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => $weekday,
            'planned_time' => $time,
            'method' => $method,
        ]);
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        $this->get(route('trmnl.dashboard'))->assertForbidden();
    }

    public function test_the_signed_feed_returns_todays_departures_and_the_week(): void
    {
        Carbon::setTestNow('2026-07-06'); // Monday

        $tom = Child::factory()->create(['name' => 'Tom']);
        $lena = Child::factory()->create(['name' => 'Lena']);
        $max = Child::factory()->create(['name' => 'Max']);
        $emma = Child::factory()->create(['name' => 'Emma']);

        $this->scheduleFor($tom, 1, '15:00', DepartureMethod::PickedUp);
        $this->scheduleFor($lena, 1, '15:00', DepartureMethod::SentHome);
        $this->scheduleFor($max, 1, '16:00', DepartureMethod::PickedUp);
        $this->scheduleFor($emma, 1, '15:00', DepartureMethod::PickedUp);

        // Max is overridden earlier today (a "changed" pickup).
        DailyDeparture::create([
            'child_id' => $max->id, 'date' => '2026-07-06',
            'planned_time' => '14:00', 'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        // Emma is away today → off the pickup list, shown as absent.
        Absence::create(['child_id' => $emma->id, 'date' => '2026-07-06', 'reason' => 'sick']);

        $response = $this->getJson(URL::signedRoute('trmnl.dashboard'))->assertOk();

        $response
            ->assertJsonPath('today.weekday', 'Montag')
            ->assertJsonPath('today.present_count', 3) // Tom, Lena, Max (not Emma)
            ->assertJsonPath('today.next_pickup', '14:00')
            // Grouped by time, ascending; 14:00 = Max (changed), 15:00 = Lena+Tom.
            ->assertJsonPath('today.departures.0.time', '14:00')
            ->assertJsonPath('today.departures.0.children.0.name', 'Max')
            ->assertJsonPath('today.departures.0.children.0.changed', true)
            ->assertJsonPath('today.departures.1.time', '15:00')
            ->assertJsonPath('today.departures.1.children.0.name', 'Lena')
            ->assertJsonPath('today.departures.1.children.1.name', 'Tom')
            ->assertJsonPath('today.absent.0.name', 'Emma')
            // Five weekdays; Monday reflects the override.
            ->assertJsonCount(5, 'week')
            ->assertJsonPath('week.0.weekday', 'Mo')
            ->assertJsonPath('week.0.is_today', true)
            ->assertJsonPath('week.0.departures.0.time', '14:00')
            ->assertJsonPath('week.0.departures.0.names.0', 'Max');
    }
}
