<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DailyProgram;
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
