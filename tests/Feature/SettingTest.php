<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\HolidayPeriod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unset_setting_falls_back_to_the_default(): void
    {
        $this->assertSame('12:00', Setting::lateChangeCutoff());
        $this->assertSame('fallback', Setting::get('nope', 'fallback'));
    }

    public function test_writing_a_setting_busts_the_cached_read(): void
    {
        // Prime the cache with the "never set" state first.
        $this->assertSame('12:00', Setting::lateChangeCutoff());

        Setting::set(Setting::LateChangeCutoff, '13:30');

        $this->assertSame('13:30', Setting::lateChangeCutoff());
        $this->assertDatabaseHas('settings', ['key' => Setting::LateChangeCutoff]);
    }

    public function test_staff_can_change_the_late_change_cutoff(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->patch(route('program.settings'), ['late_change_cutoff' => '14:15'])
            ->assertRedirect();

        $this->assertSame('14:15', Setting::lateChangeCutoff());
    }

    public function test_the_cutoff_must_be_a_time(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->patch(route('program.settings'), ['late_change_cutoff' => 'mittags'])
            ->assertSessionHasErrors('late_change_cutoff');
    }

    public function test_parents_cannot_change_the_cutoff(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Parent]))
            ->patch(route('program.settings'), ['late_change_cutoff' => '14:15'])
            ->assertForbidden();

        $this->assertSame('12:00', Setting::lateChangeCutoff());
    }

    public function test_it_falls_back_to_the_default_when_the_table_does_not_exist_yet(): void
    {
        // The schedule reads settings on every console boot — including `migrate` on a
        // fresh install, before this table is created. That must not throw.
        Schema::drop('settings');

        $this->assertSame('12:00', Setting::lateChangeCutoff());
        $this->assertSame('fallback', Setting::get('anything', 'fallback'));
    }

    public function test_the_staff_reminder_runs_half_an_hour_before_the_digest(): void
    {
        $this->assertSame('12:00', Setting::weeklyDigestTime());
        $this->assertSame('11:30', Setting::programReminderTime());

        Setting::set(Setting::WeeklyDigestTime, '15:30');

        $this->assertSame('15:00', Setting::programReminderTime());
    }

    public function test_staff_can_change_the_digest_time(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->patch(route('program.digest-time'), ['weekly_digest_time' => '16:00'])
            ->assertRedirect();

        $this->assertSame('16:00', Setting::weeklyDigestTime());
        $this->assertSame('15:30', Setting::programReminderTime());
    }

    public function test_the_digest_time_cannot_push_the_staff_reminder_into_the_previous_day(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->patch(route('program.digest-time'), ['weekly_digest_time' => '00:15'])
            ->assertSessionHasErrors('weekly_digest_time');
    }

    public function test_parents_cannot_change_the_digest_time(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Parent]))
            ->patch(route('program.digest-time'), ['weekly_digest_time' => '16:00'])
            ->assertForbidden();
    }

    public function test_staff_can_change_the_default_care_window(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->patch(route('program.care-window'), [
                'care_default_start' => '07:30',
                'care_default_end' => '15:30',
            ])
            ->assertRedirect();

        $this->assertSame(['07:30', '15:30'], Setting::careDefaultWindow());
    }

    public function test_the_default_care_window_cannot_end_before_it_starts(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->patch(route('program.care-window'), [
                'care_default_start' => '16:00',
                'care_default_end' => '08:00',
            ])
            ->assertSessionHasErrors('care_default_end');
    }

    public function test_parents_cannot_change_the_default_care_window(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Parent]))
            ->patch(route('program.care-window'), [
                'care_default_start' => '07:30',
                'care_default_end' => '15:30',
            ])
            ->assertForbidden();
    }

    public function test_a_new_ferienbetreuung_uses_the_configured_window(): void
    {
        // The setting is a starting point for newly offered days, nothing more.
        Setting::set(Setting::CareDefaultStart, '07:30');
        Setting::set(Setting::CareDefaultEnd, '15:30');

        $period = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-10-05',
            'ends_on' => '2026-10-05',
        ]);
        $period->generateCareDays();

        $day = $period->careDays()->first();
        $this->assertSame('07:30–15:30', $day->window());
    }

    public function test_the_program_page_exposes_the_care_window(): void
    {
        Setting::set(Setting::CareDefaultStart, '07:30');
        Setting::set(Setting::CareDefaultEnd, '15:30');

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('program'))
            ->assertInertia(fn ($page) => $page->where('careDefaultWindow', ['start' => '07:30', 'end' => '15:30']));
    }

    public function test_the_program_page_exposes_the_cutoff(): void
    {
        Setting::set(Setting::LateChangeCutoff, '11:45');

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('program'))
            ->assertInertia(fn (Assert $page) => $page->where('lateChangeCutoff', '11:45'));
    }
}
