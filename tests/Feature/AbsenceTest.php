<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;
use App\Models\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_parent_can_report_their_child_absent(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)->post(route('absences.store'), [
            'child_id' => $child->id,
            'from' => '2026-06-22',
            'to' => '2026-06-22',
            'reason' => 'sick',
        ])->assertRedirect();

        $this->assertDatabaseHas('absences', [
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'reason' => 'sick',
            'reported_by' => $parent->id,
        ]);
    }

    public function test_reporting_absence_removes_a_pending_pickup(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22'));
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($parent)->post(route('absences.store'), [
            'child_id' => $child->id,
            'from' => '2026-06-22',
            'to' => '2026-06-22',
            'reason' => 'away',
        ]);

        $this->assertDatabaseMissing('daily_departures', [
            'child_id' => $child->id,
            'date' => '2026-06-22',
        ]);
    }

    public function test_a_parent_cannot_report_another_childs_absence(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22'));
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $child = Child::factory()->create(); // not their child

        $this->actingAs($parent)->post(route('absences.store'), [
            'child_id' => $child->id,
            'from' => '2026-06-22',
            'to' => '2026-06-22',
            'reason' => 'sick',
        ])->assertForbidden();
    }

    public function test_past_dates_are_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22'));
        $child = Child::factory()->create();

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->post(route('absences.store'), [
                'child_id' => $child->id,
                'from' => '2026-06-20',
                'to' => '2026-06-20',
                'reason' => 'sick',
            ])->assertSessionHasErrors('from');
    }

    public function test_an_absurdly_long_date_range_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22'));
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)->post(route('absences.store'), [
            'child_id' => $child->id,
            'from' => '2026-06-22',
            'to' => '9999-12-31', // would otherwise loop ~3M times
            'reason' => 'sick',
        ])->assertSessionHasErrors('to');

        $this->assertDatabaseCount('absences', 0);
    }

    public function test_an_absent_child_is_hidden_from_the_board_and_listed(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = Child::factory()->create(['name' => 'Tom']);
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 1,
            'planned_time' => '15:00',
            'method' => DepartureMethod::PickedUp,
        ]);
        Absence::create(['child_id' => $child->id, 'date' => '2026-06-22', 'reason' => 'sick']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows', []) // not on the pickup board
                ->where('absent.0.name', 'Tom')
                ->where('absent.0.reason', 'sick'));
    }

    public function test_an_absence_can_be_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22'));
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        Absence::create(['child_id' => $child->id, 'date' => '2026-06-22', 'reason' => 'sick']);

        $this->actingAs($parent)->delete(route('absences.destroy'), [
            'child_id' => $child->id,
            'from' => '2026-06-22',
            'to' => '2026-06-22',
        ]);

        $this->assertDatabaseMissing('absences', [
            'child_id' => $child->id,
            'date' => '2026-06-22',
        ]);
    }
}
