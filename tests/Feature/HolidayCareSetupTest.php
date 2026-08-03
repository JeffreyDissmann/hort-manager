<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\HolidayPeriodType;
use App\Enums\UserRole;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Setting up a Ferienbetreuung: the period, its offered days and their times. */
class HolidayCareSetupTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // Well before the 31 July deadline used below, so registration is open.
        $this->travelTo(Carbon::parse('2026-07-20 09:00'));
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function create(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('closures.store'), array_merge([
            'name' => 'Sommer-Ferienbetreuung',
            'type' => HolidayPeriodType::Care->value,
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'registration_deadline' => '2026-07-31',
        ], $overrides));
    }

    public function test_it_offers_every_weekday_with_the_default_times(): void
    {
        $this->create()->assertRedirect();

        $period = HolidayPeriod::first();
        $this->assertTrue($period->isCare());
        $this->assertCount(5, $period->careDays);

        $this->assertSame('2026-08-03', $period->careDays->first()->date->toDateString());
        $this->assertSame('08:30', HolidayCareDay::short($period->careDays->first()->starts_at));
        $this->assertSame('16:00', HolidayCareDay::short($period->careDays->first()->ends_at));
    }

    public function test_it_skips_weekends(): void
    {
        // Mon 3rd – Mon 10th spans a weekend: 6 weekdays, not 8 days.
        $this->create(['ends_on' => '2026-08-10'])->assertRedirect();

        $dates = HolidayPeriod::first()->careDays->pluck('date')
            ->map(fn (Carbon $d): string => $d->toDateString())->all();

        $this->assertSame([
            '2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07', '2026-08-10',
        ], $dates);
    }

    public function test_the_default_window_is_settable(): void
    {
        Setting::set(Setting::CareDefaultStart, '07:30');
        Setting::set(Setting::CareDefaultEnd, '17:00');

        $this->create()->assertRedirect();

        $day = HolidayPeriod::first()->careDays->first();
        $this->assertSame('07:30', HolidayCareDay::short($day->starts_at));
        $this->assertSame('17:00', HolidayCareDay::short($day->ends_at));
    }

    public function test_a_closure_gets_no_care_days_and_no_deadline(): void
    {
        $this->actingAs($this->staff)->post(route('closures.store'), [
            'name' => 'Sommerferien',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            // Sent but meaningless for a closure — must be discarded.
            'registration_deadline' => '2026-07-31',
        ])->assertRedirect();

        $period = HolidayPeriod::first();
        $this->assertSame(HolidayPeriodType::Closed, $period->type);
        $this->assertNull($period->registration_deadline);
        $this->assertCount(0, $period->careDays);
    }

    public function test_the_deadline_cannot_be_after_the_period_starts(): void
    {
        $this->create(['registration_deadline' => '2026-08-04'])
            ->assertSessionHasErrors('registration_deadline');
    }

    public function test_extending_the_range_offers_the_new_days_and_keeps_edited_ones(): void
    {
        $this->create()->assertRedirect();
        $period = HolidayPeriod::first();

        // Staff shortened Wednesday.
        $wednesday = $period->careDays
            ->first(fn (HolidayCareDay $d): bool => $d->date->toDateString() === '2026-08-05');
        $wednesday->update(['starts_at' => '09:00', 'ends_at' => '15:00']);

        $this->actingAs($this->staff)->patch(route('closures.update', $period), [
            'name' => 'Sommer-Ferienbetreuung',
            'type' => HolidayPeriodType::Care->value,
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-12',
            'registration_deadline' => '2026-07-31',
        ])->assertRedirect();

        $period->refresh();
        $this->assertCount(8, $period->careDays); // + Mon 10th, Tue 11th, Wed 12th

        $kept = $period->careDays->first(fn (HolidayCareDay $d) => $d->date->toDateString() === '2026-08-05');
        $this->assertSame('09:00', HolidayCareDay::short($kept->starts_at));
        $this->assertSame('15:00', HolidayCareDay::short($kept->ends_at));
    }

    public function test_shrinking_the_range_drops_the_days_outside_it(): void
    {
        $this->create()->assertRedirect();
        $period = HolidayPeriod::first();

        $this->actingAs($this->staff)->patch(route('closures.update', $period), [
            'name' => 'Sommer-Ferienbetreuung',
            'type' => HolidayPeriodType::Care->value,
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-05',
            'registration_deadline' => '2026-07-31',
        ])->assertRedirect();

        $this->assertSame(3, $period->refresh()->careDays->count());
        $this->assertDatabaseMissing('holiday_care_days', ['date' => '2026-08-06']);
    }

    public function test_deleting_the_period_takes_its_days_with_it(): void
    {
        $this->create()->assertRedirect();
        $period = HolidayPeriod::first();

        $this->actingAs($this->staff)->delete(route('closures.destroy', $period))->assertRedirect();

        $this->assertDatabaseEmpty('holiday_care_days');
    }

    public function test_the_registration_window_closes_after_the_deadline(): void
    {
        $period = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'registration_deadline' => '2026-07-31',
        ]);

        // Deadline day itself is still open, the day after is not.
        $this->travelTo(Carbon::parse('2026-07-31 23:00'));
        $this->assertTrue($period->registrationIsOpen());

        $this->travelTo(Carbon::parse('2026-08-01 00:01'));
        $this->assertFalse($period->registrationIsOpen());
    }

    public function test_a_period_without_a_deadline_stays_open(): void
    {
        $period = HolidayPeriod::factory()->care()->create(['registration_deadline' => null]);

        $this->assertTrue($period->registrationIsOpen());
    }

    public function test_the_page_lists_care_periods_with_their_days(): void
    {
        $this->create()->assertRedirect();

        $this->actingAs($this->staff)
            ->get(route('closures.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('care.0.name', 'Sommer-Ferienbetreuung')
                ->where('care.0.day_count', 5)
                ->where('care.0.registration_open', true)
                ->where('care.0.days.0.starts_at', '08:30')
                ->where('careDefaults.starts_at', '08:30')
                // Care periods don't leak into the closure lists.
                ->count('upcoming', 0)
            );
    }
}
