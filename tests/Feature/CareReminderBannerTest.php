<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Child;
use App\Models\HolidayCareAnswer;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The in-app nudge to answer an open Ferienbetreuung (`pendingCare`). */
class CareReminderBannerTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-20 09:00'));

        $this->parent = User::factory()->create(['role' => UserRole::Parent]);
        $this->child = Child::factory()->create(['name' => 'Mia']);
        $this->parent->children()->attach($this->child);
    }

    private function openPeriod(): HolidayPeriod
    {
        return HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'registration_deadline' => '2026-07-31',
        ]);
    }

    public function test_an_unanswered_open_period_is_surfaced(): void
    {
        $this->openPeriod();

        $this->actingAs($this->parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingCare.0.name', 'Sommer-Ferienbetreuung')
                ->where('pendingCare.0.deadline', '2026-07-31')
                ->where('pendingCare.0.children', ['Mia'])
            );
    }

    public function test_answering_clears_it_even_with_no_days_picked(): void
    {
        $period = $this->openPeriod();

        HolidayCareAnswer::create([
            'holiday_period_id' => $period->id,
            'child_id' => $this->child->id,
            'answered_by' => $this->parent->id,
            'answered_at' => now(),
        ]);

        $this->actingAs($this->parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->count('pendingCare', 0));
    }

    public function test_only_the_unanswered_children_are_named(): void
    {
        $period = $this->openPeriod();
        $second = Child::factory()->create(['name' => 'Ben']);
        $this->parent->children()->attach($second);

        HolidayCareAnswer::create([
            'holiday_period_id' => $period->id,
            'child_id' => $this->child->id,
            'answered_by' => $this->parent->id,
            'answered_at' => now(),
        ]);

        $this->actingAs($this->parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->where('pendingCare.0.children', ['Ben']));
    }

    public function test_a_closed_registration_is_not_chased(): void
    {
        $this->openPeriod();
        $this->travelTo(Carbon::parse('2026-08-01 09:00')); // past the deadline

        $this->actingAs($this->parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->count('pendingCare', 0));
    }

    public function test_a_finished_period_is_not_chased(): void
    {
        HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-05',
            'registration_deadline' => null,
        ]);

        $this->actingAs($this->parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->count('pendingCare', 0));
    }

    public function test_a_child_not_enrolled_during_the_period_is_ignored(): void
    {
        $this->openPeriod();
        // Mia leaves before the Ferienbetreuung starts.
        $this->child->update(['active_until' => '2026-07-25']);

        $this->actingAs($this->parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->count('pendingCare', 0));
    }

    public function test_staff_are_not_chased(): void
    {
        $this->openPeriod();
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $staff->children()->attach($this->child);

        $this->actingAs($staff)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->count('pendingCare', 0));
    }

    public function test_a_closure_does_not_count_as_a_registration(): void
    {
        HolidayPeriod::factory()->create(['starts_on' => '2026-08-03', 'ends_on' => '2026-08-07']);

        $this->actingAs($this->parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->count('pendingCare', 0));
    }
}
