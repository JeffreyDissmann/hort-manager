<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\HomeworkDefault;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyProgramTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    private function parent(): User
    {
        return User::factory()->create(['role' => UserRole::Parent]);
    }

    public function test_staff_can_save_the_week_program(): void
    {
        $this->actingAs($this->staff())
            ->patch(route('program.update'), [
                'days' => [
                    ['date' => '2026-06-22', 'lunch' => 'Nudeln', 'activity' => 'Basteln'],
                    ['date' => '2026-06-23', 'lunch' => null, 'activity' => null],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_programs', [
            'date' => '2026-06-22',
            'lunch' => 'Nudeln',
            'activity' => 'Basteln',
        ]);
        // The empty day is not stored.
        $this->assertDatabaseMissing('daily_programs', ['date' => '2026-06-23']);
    }

    public function test_emptying_a_day_removes_it(): void
    {
        DailyProgram::factory()->create(['date' => '2026-06-22', 'lunch' => 'X', 'activity' => 'Y']);

        $this->actingAs($this->staff())
            ->patch(route('program.update'), [
                'days' => [['date' => '2026-06-22', 'lunch' => '', 'activity' => '']],
            ]);

        $this->assertDatabaseMissing('daily_programs', ['date' => '2026-06-22']);
    }

    public function test_program_index_lists_birthdays_per_day(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // week Mo 06-22 … Fr 06-26
        Child::factory()->create(['name' => 'Emma', 'date_of_birth' => '2018-06-24']); // Wednesday

        $this->actingAs($this->staff())
            ->get(route('program'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.2.birthdays.0.name', 'Emma')
                ->where('days.2.birthdays.0.turns', 8)
                ->where('days.0.birthdays', [])
            );
    }

    public function test_parents_cannot_manage_the_program(): void
    {
        $parent = $this->parent();

        $this->actingAs($parent)->get(route('program'))->assertForbidden();
        $this->actingAs($parent)
            ->patch(route('program.update'), ['days' => []])
            ->assertForbidden();
    }

    public function test_program_shows_on_the_board(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        DailyProgram::factory()->create([
            'date' => '2026-06-22',
            'lunch' => 'Kartoffelsuppe',
            'activity' => 'Turnhalle',
        ]);

        $this->actingAs($this->parent())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.lunch', 'Kartoffelsuppe')
                ->where('program.activity', 'Turnhalle')
            );
    }

    public function test_staff_can_set_default_homework_times(): void
    {
        $this->actingAs($this->staff())
            ->patch(route('program.defaults'), [
                'defaults' => [
                    ['weekday' => 1, 'start' => '14:00', 'end' => '15:00'],
                    ['weekday' => 2, 'start' => null, 'end' => null],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('homework_defaults', [
            'weekday' => 1,
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]);
        $this->assertDatabaseMissing('homework_defaults', ['weekday' => 2]);
    }

    public function test_index_falls_back_to_the_weekday_homework_default(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday → day index 0
        HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

        $this->actingAs($this->staff())
            ->get(route('program'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.0.homework_start', '14:00')
                ->where('days.0.homework_end', '15:00')
                ->where('homeworkDefaults.0.start', '14:00')
            );
    }

    public function test_homework_equal_to_default_is_not_stored_as_override(): void
    {
        HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

        $this->actingAs($this->staff())
            ->patch(route('program.update'), [
                'days' => [[
                    'date' => '2026-06-22', // Monday
                    'homework_start' => '14:00',
                    'homework_end' => '15:00',
                ]],
            ]);

        // Equal to the default → no override row stored.
        $this->assertDatabaseMissing('daily_programs', ['date' => '2026-06-22']);
    }

    public function test_no_homework_suppresses_the_weekday_default(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

        $this->actingAs($this->staff())
            ->patch(route('program.update'), [
                'days' => [[
                    'date' => '2026-06-22', // Monday
                    'homework_none' => true,
                ]],
            ]);

        // The explicit "keine Hausaufgaben" is recorded to override the default…
        $this->assertDatabaseHas('daily_programs', [
            'date' => '2026-06-22',
            'homework_none' => true,
        ]);

        // …and the editor shows no homework that day, despite the weekday default.
        $this->actingAs($this->staff())
            ->get(route('program'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.0.homework_start', null)
                ->where('days.0.homework_end', null)
            );
    }

    public function test_homework_override_is_stored_when_it_differs(): void
    {
        HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

        $this->actingAs($this->staff())
            ->patch(route('program.update'), [
                'days' => [[
                    'date' => '2026-06-22', // Monday
                    'homework_start' => '15:00',
                    'homework_end' => '16:00',
                ]],
            ]);

        $this->assertDatabaseHas('daily_programs', [
            'date' => '2026-06-22',
            'homework_start' => '15:00',
            'homework_end' => '16:00',
        ]);
    }

    public function test_homework_shows_on_the_board(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

        $this->actingAs($this->parent())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.homework_start', '14:00')
                ->where('program.homework_end', '15:00')
            );
    }

    public function test_program_shows_on_the_abholplan(): void
    {
        $this->travelTo(Carbon::parse('2026-06-24')); // Wednesday, week starts Mo 2026-06-22
        DailyProgram::factory()->create([
            'date' => '2026-06-22', // Monday → index 0
            'lunch' => 'Reis',
            'activity' => 'Spielplatz',
        ]);

        $this->actingAs($this->parent())
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('program.0.lunch', 'Reis')
                ->where('program.0.activity', 'Spielplatz')
            );
    }
}
