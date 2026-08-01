<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DailyProgram;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\HomeworkDefault;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A Ferienbetreuung day on the Tagesprogramm: Essen and Aktivität as usual, the
 * Betreuungszeit instead of Hausaufgaben — there is no school to bring any home from.
 */
class CareProgramPageTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            HomeworkDefault::create(['weekday' => $weekday, 'start_time' => '14:00', 'end_time' => '15:00']);
        }
    }

    private function careWeek(): HolidayPeriod
    {
        $period = HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
        ]);
        $period->generateCareDays();

        return $period;
    }

    public function test_a_care_day_shows_its_betreuungszeit(): void
    {
        $this->careWeek();

        $this->actingAs($this->staff)
            ->get(route('program'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.0.care.name', 'Sommer-Ferienbetreuung')
                ->where('days.0.care.starts_at', '08:30')
                ->where('days.0.care.ends_at', '16:30')
                // No school → no homework slot offered, despite the weekday default.
                ->where('days.0.homework_start', null)
                ->where('days.0.homework_end', null)
            );
    }

    public function test_saving_the_week_stores_the_betreuungszeit_and_the_content(): void
    {
        $this->careWeek();

        $this->actingAs($this->staff)
            ->patch(route('program.update'), [
                'days' => [[
                    'date' => '2026-08-03',
                    'lunch' => 'Nudeln',
                    'activity' => 'Schwimmbad',
                    'care_starts_at' => '09:00',
                    'care_ends_at' => '15:00',
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_programs', [
            'date' => '2026-08-03',
            'lunch' => 'Nudeln',
            'activity' => 'Schwimmbad',
        ]);

        $day = HolidayCareDay::firstWhere('date', '2026-08-03');
        $this->assertSame('09:00', HolidayCareDay::short($day->starts_at));
        $this->assertSame('15:00', HolidayCareDay::short($day->ends_at));
    }

    public function test_a_care_day_never_stores_homework(): void
    {
        $this->careWeek();

        $this->actingAs($this->staff)
            ->patch(route('program.update'), [
                'days' => [[
                    'date' => '2026-08-03',
                    'lunch' => 'Nudeln',
                    // Sent but meaningless on a care day.
                    'homework_start' => '14:00',
                    'homework_end' => '15:00',
                ]],
            ])
            ->assertRedirect();

        $program = DailyProgram::firstWhere('date', '2026-08-03');
        $this->assertNull($program->homework_start);
        $this->assertFalse((bool) $program->homework_none);
    }

    public function test_the_weekly_plan_draws_no_homework_band_on_a_care_day(): void
    {
        $this->careWeek();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                // The weekday default would otherwise reach into the holidays.
                ->where('program.0.homework_start', null)
                ->where('program.0.homework_end', null)
            );
    }

    public function test_the_weekly_plan_still_shows_food_and_activity_on_a_care_day(): void
    {
        $this->careWeek();
        DailyProgram::create(['date' => '2026-08-03', 'lunch' => 'Nudeln', 'activity' => 'Schwimmbad']);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.0.lunch', 'Nudeln')
                ->where('program.0.activity', 'Schwimmbad')
            );
    }

    public function test_the_lunch_reminder_ignores_care_days(): void
    {
        Notification::fake();
        $this->careWeek();

        $this->assertSame([], DailyProgram::weekdaysWithoutLunch(Carbon::today()));

        $this->artisan('program:remind-missing')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_a_half_care_week_still_chases_the_normal_days(): void
    {
        $period = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-04',
        ]);
        $period->generateCareDays();

        $missing = array_map(
            fn (Carbon $d): string => $d->toDateString(),
            DailyProgram::weekdaysWithoutLunch(Carbon::today()),
        );

        $this->assertSame(['2026-08-05', '2026-08-06', '2026-08-07'], $missing);
    }

    public function test_a_normal_week_is_unchanged(): void
    {
        $this->actingAs($this->staff)
            ->get(route('program'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.0.care', null)
                ->where('days.0.homework_start', '14:00')
            );
    }
}
