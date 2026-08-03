<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Notifications\ChildDeparted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyBoardTest extends TestCase
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

    private function scheduledChild(int $weekday, string $time = '16:00'): Child
    {
        $child = Child::factory()->create();
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => $weekday,
            'planned_time' => $time,
            'method' => DepartureMethod::PickedUp,
        ]);

        return $child;
    }

    public function test_board_seeds_a_row_from_the_stammplan_for_today(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = $this->scheduledChild(weekday: 1, time: '16:00');

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Board/Index')
                ->where('date.is_today', true)
                ->where('canMark', true)
                ->has('rows', 1)
                ->where('rows.0.name', $child->name)
                ->where('rows.0.planned_time', '16:00')
                ->where('rows.0.status', DepartureStatus::Present->value)
                ->where('rows.0.is_overridden', false)
            );

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'status' => DepartureStatus::Present->value,
        ]);
    }

    public function test_board_rows_flag_a_parents_own_children(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $mine = $this->scheduledChild(weekday: 1);
        $other = $this->scheduledChild(weekday: 1);

        $parent = $this->parent();
        $parent->children()->attach($mine);

        $this->actingAs($parent)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows', fn ($rows) => collect($rows)->firstWhere('child_id', $mine->id)['is_own'] === true
                    && collect($rows)->firstWhere('child_id', $other->id)['is_own'] === false)
            );
    }

    public function test_board_flags_a_childs_birthday(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = $this->scheduledChild(weekday: 1);
        $child->update(['date_of_birth' => '2019-06-22']); // same month-day → turns 7

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.birthday', 7));
    }

    public function test_children_not_scheduled_that_day_are_not_on_the_board(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $this->scheduledChild(weekday: 3); // Wednesday only

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    }

    public function test_on_the_weekend_the_board_targets_the_next_weekday(): void
    {
        $this->travelTo(Carbon::parse('2026-06-27')); // Saturday
        $this->scheduledChild(weekday: 1); // Monday

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('date.iso', '2026-06-29') // next Monday
                ->where('date.is_today', false)
                ->has('rows', 1)
            );
    }

    public function test_staff_can_mark_a_child_as_picked_up_and_undo(): void
    {
        $staff = $this->staff();
        $departure = DailyDeparture::factory()->create(['status' => DepartureStatus::Present]);

        $this->actingAs($staff)
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::PickedUp->value])
            ->assertRedirect();

        $departure->refresh();
        $this->assertSame(DepartureStatus::PickedUp, $departure->status);
        $this->assertNotNull($departure->left_at);
        $this->assertSame($staff->id, $departure->marked_by);

        // Undo back to present clears the timestamp and marker.
        $this->actingAs($staff)
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::Present->value]);

        $departure->refresh();
        $this->assertSame(DepartureStatus::Present, $departure->status);
        $this->assertNull($departure->left_at);
        $this->assertNull($departure->marked_by);
    }

    public function test_parents_cannot_mark_departures(): void
    {
        $departure = DailyDeparture::factory()->create();

        $this->actingAs($this->parent())
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::PickedUp->value])
            ->assertForbidden();
    }

    public function test_the_board_lists_children_who_are_hortfrei_today(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday (weekday 1)

        // Comes Tuesdays only → „Hortfrei" on Monday, but still has a plan.
        $tuesdayChild = Child::factory()->create(['name' => 'Tuesday Kid']);
        WeeklySchedule::create(['child_id' => $tuesdayChild->id, 'weekday' => 2, 'planned_time' => '15:00', 'method' => DepartureMethod::PickedUp]);

        // Comes Mondays → on the board, not in the Hortfrei list.
        $mondayChild = $this->scheduledChild(weekday: 1);

        // No Stammplan at all → unplanned, not „Hortfrei".
        Child::factory()->create(['name' => 'Unplanned Kid']);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('hortfrei', 1)
                ->where('hortfrei.0.name', 'Tuesday Kid')
                ->where('hortfrei.0.can_manage', true) // staff can jump to any child
                ->where('rows.0.name', $mondayChild->name)
            );
    }

    public function test_a_hortfrei_child_with_a_same_day_override_is_not_listed_as_hortfrei(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday (weekday 1)

        // Comes Tuesdays only → normally „Hortfrei" on Monday …
        $nora = Child::factory()->create(['name' => 'Nora']);
        WeeklySchedule::create(['child_id' => $nora->id, 'weekday' => 2, 'planned_time' => '15:00', 'method' => DepartureMethod::PickedUp]);

        // … but a manual override adds her for today, so she IS at the Hort.
        DailyDeparture::create([
            'child_id' => $nora->id,
            'date' => '2026-06-22',
            'planned_time' => '13:45',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('hortfrei', 0)          // not listed as Hortfrei …
                ->where('rows.0.name', 'Nora') // … she's a board row instead
            );
    }

    public function test_the_board_seeds_the_stammplan_time_qualifier(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = Child::factory()->create();
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => 1,
            'planned_time' => '15:00',
            'method' => DepartureMethod::SentHome,
            'time_qualifier' => TimeQualifier::By, // „bis"
        ]);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.name', $child->name)
                ->where('rows.0.qualifier', TimeQualifier::By->value)
                ->where('rows.0.qualifier_prefix', TimeQualifier::By->prefix())
            );

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'time_qualifier' => TimeQualifier::By->value,
        ]);
    }

    public function test_a_board_override_can_set_a_time_qualifier(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        $departure = DailyDeparture::factory()->create(['child_id' => $child->id, 'planned_time' => '16:00']);

        $this->actingAs($parent)
            ->patch(route('board.override', $departure), [
                'planned_time' => '15:30',
                'planned_method' => DepartureMethod::SentHome->value,
                'time_qualifier' => TimeQualifier::From->value,
            ])
            ->assertRedirect();

        $this->assertSame(TimeQualifier::From, $departure->refresh()->time_qualifier);
    }

    public function test_a_picked_up_override_clears_any_time_qualifier(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        $departure = DailyDeparture::factory()->create([
            'child_id' => $child->id,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::SentHome,
            'time_qualifier' => TimeQualifier::From,
        ]);

        $this->actingAs($parent)
            ->patch(route('board.override', $departure), [
                'planned_time' => '16:00',
                'planned_method' => DepartureMethod::PickedUp->value,
                'time_qualifier' => TimeQualifier::By->value, // ignored for picked_up
            ])
            ->assertRedirect();

        $this->assertNull($departure->refresh()->time_qualifier);
    }

    public function test_a_parent_can_override_their_own_childs_plan_today(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        $departure = DailyDeparture::factory()->create([
            'child_id' => $child->id,
            'planned_time' => '16:00',
        ]);

        $this->actingAs($parent)
            ->patch(route('board.override', $departure), [
                'planned_time' => '14:30',
                'planned_method' => DepartureMethod::PickedUp->value,
            ])
            ->assertRedirect();

        $this->assertSame('14:30', substr((string) $departure->refresh()->planned_time, 0, 5));
    }

    public function test_a_parent_cannot_override_another_childs_plan(): void
    {
        $departure = DailyDeparture::factory()->create(); // not their child

        $this->actingAs($this->parent())
            ->patch(route('board.override', $departure), ['planned_time' => '14:30'])
            ->assertForbidden();
    }

    public function test_an_override_is_flagged_on_the_board(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = $this->scheduledChild(weekday: 1, time: '16:00');

        // Seed today's row, then override it to an earlier time.
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'planned_time' => '14:30',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.planned_time', '14:30')
                ->where('rows.0.is_overridden', true)
            );
    }

    public function test_marking_a_child_off_dms_their_slack_guardians(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-06-22'));

        $child = $this->scheduledChild(weekday: 1);
        $withSlack = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $noSlack = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => null]);
        $child->guardians()->attach([$withSlack->id, $noSlack->id]);

        $departure = DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'planned_time' => '16:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff())
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::PickedUp->value])
            ->assertRedirect();

        Notification::assertSentTo($withSlack, ChildDeparted::class);
        Notification::assertNotSentTo($noSlack, ChildDeparted::class);
    }

    public function test_marking_a_child_present_again_sends_nothing(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-06-22'));

        $child = $this->scheduledChild(weekday: 1);
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $child->guardians()->attach($guardian);

        $departure = DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'planned_time' => '16:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::PickedUp,
            'left_at' => now(),
        ]);

        $this->actingAs($this->staff())
            ->patch(route('board.mark', $departure), ['status' => DepartureStatus::Present->value]);

        Notification::assertNothingSent();
    }

    public function test_the_board_lists_who_is_on_todays_excursion(): void
    {
        Carbon::setTestNow('2026-07-06'); // Montag

        $emma = Child::factory()->create(['name' => 'Emma']);
        $mia = Child::factory()->create(['name' => 'Mia']);
        Child::factory()->create(['name' => 'Nein-Kind']); // invited but not attending

        $excursion = Excursion::factory()->create(['date' => '2026-07-06']);
        $excursion->children()->attach($emma->id, ['response' => true]);
        $excursion->children()->attach($mia->id, ['response' => true]);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('excursions', 1)
                ->where('excursions.0.child_count', 2)
                ->has('excursions.0.children', 2)
                ->where('excursions.0.children.0', 'Emma') // sorted
                ->where('excursions.0.children.1', 'Mia')
                ->etc()
            );
    }

    public function test_a_future_day_shows_a_planned_row_without_persisting_it(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = $this->scheduledChild(weekday: 3, time: '15:00'); // Wednesday
        $future = '2026-06-24'; // Wednesday

        $this->actingAs($this->staff())
            ->get(route('board', ['date' => $future]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Board/Index')
                ->where('date.iso', $future)
                ->where('date.is_today', false)
                ->where('date.editable', true)
                ->where('canMark', false) // no live marking off today
                ->has('rows', 1)
                ->where('rows.0.name', $child->name)
                ->where('rows.0.planned_time', '15:00')
                ->where('rows.0.is_overridden', false)
                ->where('rows.0.can_override', true) // staff may still adjust a future day
                ->etc()
            );

        // Merely viewing a future day must not seed a DailyDeparture row.
        $this->assertDatabaseMissing('daily_departures', [
            'child_id' => $child->id,
            'date' => $future,
        ]);
    }

    public function test_a_future_override_shows_as_overridden(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $child = $this->scheduledChild(weekday: 3, time: '15:00'); // Wednesday
        $future = '2026-06-24';

        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $future,
            'planned_time' => '17:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff())
            ->get(route('board', ['date' => $future]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.planned_time', '17:00')
                ->where('rows.0.is_overridden', true)
                ->etc()
            );
    }

    public function test_a_past_day_is_read_only_history(): void
    {
        $this->travelTo(Carbon::parse('2026-06-24')); // Wednesday
        $child = $this->scheduledChild(weekday: 1, time: '16:00'); // Monday
        $past = '2026-06-22'; // Monday, two days ago

        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $past,
            'planned_time' => '16:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::PickedUp,
            'left_at' => Carbon::parse($past.' 16:05'),
        ]);

        $this->actingAs($this->staff())
            ->get(route('board', ['date' => $past]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('date.iso', $past)
                ->where('date.editable', false)
                ->where('canMark', false)
                ->has('rows', 1)
                ->where('rows.0.status', DepartureStatus::PickedUp->value)
                ->where('rows.0.left_at', '16:05')
                ->where('rows.0.can_override', false) // past is read-only
                ->etc()
            );
    }

    public function test_navigating_before_the_retention_floor_is_clamped(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday
        $floor = Carbon::today()->subWeeks((int) config('hort.retention_weeks'))->startOfDay();
        while ($floor->isWeekend()) {
            $floor->addDay();
        }

        $this->actingAs($this->staff())
            ->get(route('board', ['date' => '2020-01-01'])) // long before the floor
            ->assertInertia(fn (Assert $page) => $page
                ->where('date.iso', $floor->toDateString())
                ->where('date.prev', null) // back chevron disabled at the boundary
                ->etc()
            );
    }

    public function test_a_weekend_date_advances_to_the_next_weekday(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday

        $this->actingAs($this->staff())
            ->get(route('board', ['date' => '2026-06-27'])) // Saturday
            ->assertInertia(fn (Assert $page) => $page
                ->where('date.iso', '2026-06-29') // next Monday
                ->etc()
            );
    }

    public function test_parents_never_mark_and_staff_only_mark_today(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22'));
        $this->scheduledChild(weekday: 3);

        $this->actingAs($this->staff())
            ->get(route('board', ['date' => '2026-06-24'])) // future
            ->assertInertia(fn (Assert $page) => $page->where('canMark', false)->etc());

        $this->actingAs($this->parent())
            ->get(route('board')) // today
            ->assertInertia(fn (Assert $page) => $page->where('canMark', false)->etc());
    }

    public function test_marking_a_past_departure_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-06-24')); // Wednesday
        $child = $this->scheduledChild(weekday: 1, time: '16:00');
        $past = DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-22', // Monday, in the past
            'planned_time' => '16:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff())
            ->patch(route('board.mark', $past), ['status' => DepartureStatus::PickedUp->value])
            ->assertForbidden(); // history can't be rewritten via the board
    }

    public function test_overriding_a_past_departure_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-06-24'));
        $child = $this->scheduledChild(weekday: 1, time: '16:00');
        $parent = $this->parent();
        $child->guardians()->attach($parent);
        $past = DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'planned_time' => '16:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($parent)
            ->patch(route('board.override', $past), [
                'planned_time' => '17:00',
                'planned_method' => DepartureMethod::PickedUp->value,
            ])
            ->assertForbidden();
    }
}
