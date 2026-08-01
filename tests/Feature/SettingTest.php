<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_the_program_page_exposes_the_cutoff(): void
    {
        Setting::set(Setting::LateChangeCutoff, '11:45');

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('program'))
            ->assertInertia(fn (Assert $page) => $page->where('lateChangeCutoff', '11:45'));
    }
}
