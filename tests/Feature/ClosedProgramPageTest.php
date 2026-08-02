<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HolidayPeriod;
use App\Models\HomeworkDefault;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Schließzeiten on the Tagesprogramm page, and the homework band they must not draw. */
class ClosedProgramPageTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);

        // A homework slot on every weekday — the default that used to leak into
        // closed days on the Wochenplan timeline.
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            HomeworkDefault::create([
                'weekday' => $weekday,
                'start_time' => '14:00',
                'end_time' => '15:00',
            ]);
        }
    }

    public function test_a_closed_day_offers_no_fields_but_names_the_closure(): void
    {
        HolidayPeriod::factory()->onDay('2026-08-05')->create(['name' => 'Fortbildung']);

        $this->actingAs($this->staff)
            ->get(route('program'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.2.closed', 'Fortbildung')
                ->where('days.1.closed', null)
            );
    }

    public function test_saving_the_week_skips_closed_days(): void
    {
        HolidayPeriod::factory()->onDay('2026-08-05')->create();

        $this->actingAs($this->staff)
            ->patch(route('program.update'), [
                'days' => [
                    ['date' => '2026-08-04', 'lunch' => 'Nudeln', 'activity' => null],
                    ['date' => '2026-08-05', 'lunch' => 'Sollte nicht gehen', 'activity' => 'Auch nicht'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_programs', ['date' => '2026-08-04', 'lunch' => 'Nudeln']);
        $this->assertDatabaseMissing('daily_programs', ['date' => '2026-08-05']);
    }

    public function test_saving_clears_a_program_entered_before_the_closure(): void
    {
        DailyProgram::create(['date' => '2026-08-05', 'lunch' => 'Nudeln', 'activity' => 'Basteln']);
        HolidayPeriod::factory()->onDay('2026-08-05')->create();

        $this->actingAs($this->staff)
            ->patch(route('program.update'), [
                'days' => [['date' => '2026-08-05', 'lunch' => 'Nudeln', 'activity' => 'Basteln']],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('daily_programs', ['date' => '2026-08-05']);
    }

    public function test_the_weekly_plan_draws_no_homework_band_on_a_closed_day(): void
    {
        // A child with a plan, so the timetable has rows to draw at all.
        $child = Child::factory()->create();
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WeeklySchedule::create([
                'child_id' => $child->id,
                'weekday' => $weekday,
                'planned_time' => '15:00',
                'method' => DepartureMethod::PickedUp,
            ]);
        }

        HolidayPeriod::factory()->onDay('2026-08-05')->create(['name' => 'Fortbildung']);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                // The weekday default would otherwise put 14:00–15:00 on every day.
                ->where('program.2.homework_start', null)
                ->where('program.2.homework_end', null)
                ->where('program.2.lunch', null)
                ->where('program.1.homework_start', '14:00')
            );
    }

    public function test_a_fully_closed_week_draws_no_bands_at_all(): void
    {
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-07')->create(['name' => 'Sommerferien']);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(function (Assert $page) {
                foreach ($page->toArray()['props']['program'] as $day) {
                    $this->assertNull($day['homework_start']);
                    $this->assertNull($day['lunch']);
                    $this->assertNull($day['activity']);
                }
            });
    }

    public function test_an_excursion_on_a_closed_day_is_not_advertised(): void
    {
        Excursion::create(['name' => 'Zoo', 'date' => '2026-08-05', 'depart_at' => '09:00', 'return_at' => '15:00']);
        HolidayPeriod::factory()->onDay('2026-08-05')->create();

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page->count('activities.2', 0));
    }

    public function test_an_open_week_keeps_its_homework_bands(): void
    {
        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.2.homework_start', '14:00')
                ->where('program.2.homework_end', '15:00')
            );
    }

    public function test_a_changed_homework_time_still_wins_over_the_weekday_default(): void
    {
        // Wednesday moved to 15:30–16:30; the other days keep the 14:00 default.
        DailyProgram::create([
            'date' => '2026-08-05',
            'homework_start' => '15:30',
            'homework_end' => '16:30',
        ]);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.2.homework_start', '15:30')
                ->where('program.2.homework_end', '16:30')
                ->where('program.1.homework_start', '14:00')
            );
    }

    public function test_a_day_marked_as_having_no_homework_draws_no_band(): void
    {
        DailyProgram::create(['date' => '2026-08-05', 'homework_none' => true]);

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.2.homework_start', null)
                ->where('program.1.homework_start', '14:00')
            );
    }
}
