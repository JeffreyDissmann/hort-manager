<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** On a Schließzeit the board shows „geschlossen" and seeds nothing. */
class ClosedBoardTest extends TestCase
{
    use RefreshDatabase;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday

        $this->child = Child::factory()->create(['name' => 'Mia']);
        WeeklySchedule::create([
            'child_id' => $this->child->id,
            'weekday' => 1,
            'planned_time' => '15:00',
            'method' => DepartureMethod::PickedUp,
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    private function closeToday(): HolidayPeriod
    {
        return HolidayPeriod::factory()->between('2026-08-03', '2026-08-07')->create([
            'name' => 'Sommerferien',
            'note' => 'Hort öffnet wieder am 10. August',
        ]);
    }

    public function test_a_closed_day_reports_the_closure_instead_of_a_plan(): void
    {
        $this->closeToday();

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Board/Index')
                ->where('closure.name', 'Sommerferien')
                ->where('closure.note', 'Hort öffnet wieder am 10. August')
                ->missing('rows')
            );
    }

    public function test_it_seeds_no_departures_on_a_closed_day(): void
    {
        $this->closeToday();

        $this->actingAs($this->staff())->get(route('board'))->assertOk();

        // The normal board would have created a row for Mia's Monday Stammplan.
        $this->assertDatabaseEmpty('daily_departures');
    }

    public function test_an_open_day_is_untouched(): void
    {
        // The closure covers next week, not today.
        HolidayPeriod::factory()->between('2026-08-10', '2026-08-14')->create();

        $this->actingAs($this->staff())
            ->get(route('board'))
            // No closure prop at all on a normal day; the page defaults it to null.
            ->assertInertia(fn (Assert $page) => $page->missing('closure')->has('rows', 1));

        $this->assertDatabaseCount('daily_departures', 1);
    }

    public function test_the_last_day_of_a_closure_still_closes(): void
    {
        HolidayPeriod::factory()->between('2026-07-27', '2026-08-03')->create(['name' => 'Bis heute']);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->where('closure.name', 'Bis heute'));
    }

    public function test_marking_a_departure_is_refused_on_a_closed_day(): void
    {
        // A row seeded before the closure was entered is orphaned, not markable.
        $departure = DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-08-03',
            'status' => DepartureStatus::Present,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
        ]);
        $this->closeToday();

        $this->actingAs($this->staff())
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::PickedUp->value])
            ->assertForbidden();

        $this->assertNull($departure->fresh()->left_at);
    }

    public function test_overriding_a_plan_is_refused_on_a_closed_day(): void
    {
        $departure = DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-08-03',
            'status' => DepartureStatus::Present,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
        ]);
        $this->closeToday();

        $this->actingAs($this->staff())
            ->patch(route('board.override', $departure), [
                'planned_time' => '16:00',
                'planned_method' => 'picked_up',
            ])
            ->assertForbidden();
    }

    public function test_parents_see_the_closure_too(): void
    {
        $this->closeToday();
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $parent->children()->attach($this->child);

        $this->actingAs($parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->where('closure.name', 'Sommerferien'));
    }
}
