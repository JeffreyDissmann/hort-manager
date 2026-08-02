<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Rules\NotDuringClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Edges around Schließzeiten that the per-surface tests don't reach. */
class ClosureIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->child = Child::factory()->create(['name' => 'Mia']);
        WeeklySchedule::create([
            'child_id' => $this->child->id,
            'weekday' => 1,
            'planned_time' => '15:00',
            'method' => DepartureMethod::PickedUp,
        ]);
    }

    public function test_deleting_a_closure_opens_the_day_again(): void
    {
        $closure = HolidayPeriod::factory()->onDay('2026-08-03')->create();

        $this->actingAs($this->staff)->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->has('closure'));
        $this->assertDatabaseEmpty('daily_departures');

        $this->actingAs($this->staff)->delete(route('closures.destroy', $closure))->assertRedirect();

        // The board seeds from the Stammplan again, as if the closure never was.
        $this->actingAs($this->staff)->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->missing('closure')->has('rows', 1));
        $this->assertDatabaseCount('daily_departures', 1);
    }

    public function test_two_closures_on_the_same_day_do_not_collide(): void
    {
        // Overlapping entries are a data-entry accident, not an error — the day is
        // shut either way, and one of the names is shown.
        HolidayPeriod::factory()->onDay('2026-08-03')->create(['name' => 'Brückentag']);
        HolidayPeriod::factory()->between('2026-08-01', '2026-08-05')->create(['name' => 'Sommerferien']);

        $this->actingAs($this->staff)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->has('closure.name'));

        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn (Assert $page) => $page->has('closedDays.2026-08-03'));
    }

    public function test_a_closure_may_be_entered_for_a_past_date(): void
    {
        // Backfilling last month's Ferien is legitimate; only planning is forward-only.
        $this->actingAs($this->staff)
            ->post(route('closures.store'), [
                'name' => 'Osterferien',
                'starts_on' => '2026-04-06',
                'ends_on' => '2026-04-10',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('holiday_periods', ['name' => 'Osterferien']);
    }

    public function test_the_closure_rule_ignores_an_empty_date(): void
    {
        // `required` is a separate rule's job — this one must not fire on null.
        $validator = Validator::make(['date' => null], ['date' => [new NotDuringClosure]]);

        $this->assertFalse($validator->fails());
    }

    public function test_clearing_an_absence_on_a_closed_day_still_works(): void
    {
        // Reported before the closure was entered; the parent should still be able to
        // undo it rather than be stuck with a stale Krankmeldung.
        Absence::report($this->child, '2026-08-03', AbsenceReason::Sick, $this->staff->id);
        HolidayPeriod::factory()->onDay('2026-08-03')->create();

        $this->actingAs($this->staff)
            ->delete(route('absences.destroy'), [
                'child_id' => $this->child->id,
                'from' => '2026-08-03',
                'to' => '2026-08-03',
            ])
            ->assertRedirect();

        $this->assertDatabaseEmpty('absences');
    }

    public function test_closed_days_between_clamps_to_the_range_asked_for(): void
    {
        // An overhanging period would otherwise leak days past the week and break the
        // „is the whole week closed?" count in the digest.
        HolidayPeriod::factory()->between('2026-07-27', '2026-08-14')->create(['name' => 'Lang']);

        $days = HolidayPeriod::closedDaysBetween('2026-08-03', '2026-08-07');

        $this->assertSame([
            '2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07',
        ], array_keys($days));
    }

    public function test_a_closure_covering_only_the_weekend_closes_nothing_on_weekdays(): void
    {
        HolidayPeriod::factory()->between('2026-08-08', '2026-08-09')->create();

        $this->assertFalse(HolidayPeriod::closesOn('2026-08-07'));
        $this->assertTrue(HolidayPeriod::closesOn('2026-08-08'));
        $this->assertSame([], HolidayPeriod::closedDaysBetween('2026-08-03', '2026-08-07'));
    }
}
