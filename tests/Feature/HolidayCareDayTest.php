<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\HolidayPeriodType;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Staff adjust a single offered day: its Betreuungszeit and what's planned. */
class HolidayCareDayTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private HolidayPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-20 09:00'));
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->period = HolidayPeriod::factory()->care()->create([
            'type' => HolidayPeriodType::Care,
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
        ]);
        $this->period->generateCareDays();
    }

    public function test_staff_can_change_a_days_betreuungszeit(): void
    {
        $day = HolidayCareDay::firstWhere('date', '2026-08-05');

        $this->actingAs($this->staff)
            ->patch(route('care-days.update', $day), ['starts_at' => '09:00', 'ends_at' => '15:00'])
            ->assertRedirect();

        $day->refresh();
        $this->assertSame('09:00', HolidayCareDay::short($day->starts_at));
        $this->assertSame('15:00', HolidayCareDay::short($day->ends_at));
    }

    public function test_a_moved_end_time_moves_the_sign_ups_with_it(): void
    {
        $day = HolidayCareDay::firstWhere('date', '2026-08-05');
        $child = Child::factory()->create(['name' => 'Mia']);

        // Signed up for the day as offered: pickup at the end of the Betreuungszeit.
        $signUp = DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $day->date->toDateString(),
            'holiday_care_day_id' => $day->id,
            'planned_time' => $day->ends_at,
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff)
            ->patch(route('care-days.update', $day), ['starts_at' => '08:30', 'ends_at' => '15:00'])
            ->assertRedirect();

        $this->assertSame('15:00', substr((string) $signUp->refresh()->planned_time, 0, 5));
    }

    public function test_a_pickup_the_family_chose_is_left_alone(): void
    {
        $day = HolidayCareDay::firstWhere('date', '2026-08-05');
        $child = Child::factory()->create(['name' => 'Ben']);

        $signUp = DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $day->date->toDateString(),
            'holiday_care_day_id' => $day->id,
            'planned_time' => '13:00', // deliberately earlier than the Betreuungszeit
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff)
            ->patch(route('care-days.update', $day), ['starts_at' => '08:30', 'ends_at' => '15:00'])
            ->assertRedirect();

        $this->assertSame('13:00', substr((string) $signUp->refresh()->planned_time, 0, 5));
    }

    public function test_a_day_cannot_end_before_it_starts(): void
    {
        $day = HolidayCareDay::first();

        $this->actingAs($this->staff)
            ->patch(route('care-days.update', $day), ['starts_at' => '16:00', 'ends_at' => '09:00'])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_staff_can_stop_offering_a_single_day(): void
    {
        $day = HolidayCareDay::firstWhere('date', '2026-08-05');

        $this->actingAs($this->staff)->delete(route('care-days.destroy', $day))->assertRedirect();

        $this->assertDatabaseMissing('holiday_care_days', ['date' => '2026-08-05']);
        $this->assertSame(4, HolidayCareDay::count());
    }

    public function test_parents_cannot_touch_the_offered_days(): void
    {
        $day = HolidayCareDay::first();
        $parent = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($parent)
            ->patch(route('care-days.update', $day), ['starts_at' => '06:00', 'ends_at' => '20:00'])
            ->assertForbidden();

        $this->actingAs($parent)->delete(route('care-days.destroy', $day))->assertForbidden();
    }
}
