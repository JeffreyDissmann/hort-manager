<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Notifications\LateChange;
use App\Notifications\ProgramMissing;
use App\Notifications\WeeklyDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The seams between Ferienbetreuung and the rest of the app: signing up is the only
 * way in, un-offering a day takes its sign-ups with it, and everything else that
 * happens on a normal Hort day keeps happening on a care day.
 */
class CareIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $parent;

    private Child $child;

    private HolidayPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday

        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->parent = User::factory()->create(['role' => UserRole::Parent]);
        $this->child = Child::factory()->create(['name' => 'Mia']);
        $this->parent->children()->attach($this->child);

        WeeklySchedule::create([
            'child_id' => $this->child->id,
            'weekday' => 3,
            'planned_time' => '15:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        // Wed–Fri this week, sign-up already closed.
        $this->period = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-05',
            'ends_on' => '2026-08-07',
            'registration_deadline' => '2026-08-01',
        ]);
        $this->period->generateCareDays();
    }

    private function signUp(string $date): DailyDeparture
    {
        $day = HolidayCareDay::firstWhere('date', $date);

        return DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => $date,
            'holiday_care_day_id' => $day->id,
            'planned_time' => $day->ends_at,
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);
    }

    // --- signing up is the only way in -------------------------------------------

    public function test_a_parent_cannot_plan_a_care_day_they_are_not_signed_up_for(): void
    {
        // Otherwise this registers the child through the back door, deadline and all.
        $this->actingAs($this->parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-05',
                'planned_time' => '16:00',
                'planned_method' => 'picked_up',
            ])
            ->assertForbidden();

        $this->assertDatabaseEmpty('daily_departures');
    }

    public function test_a_parent_may_adjust_a_care_day_they_signed_up_for(): void
    {
        $this->signUp('2026-08-05');

        $this->actingAs($this->parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-05',
                'planned_time' => '14:00',
                'planned_method' => 'picked_up',
            ])
            ->assertRedirect();

        $this->assertSame('14:00', substr(
            (string) DailyDeparture::firstWhere('date', '2026-08-05')->planned_time, 0, 5,
        ));
    }

    public function test_staff_may_plan_an_unregistered_care_day(): void
    {
        // Staff can sign anyone up on /care at any time, so the same via the plan.
        $this->actingAs($this->staff)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-05',
                'planned_time' => '16:00',
                'planned_method' => 'picked_up',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('daily_departures', 1);
    }

    public function test_a_sign_up_cannot_be_cancelled_by_resetting_the_day(): void
    {
        // „Auf Standard zurücksetzen" deletes the row — which on a care day is the
        // child's place, past a deadline that /care would have refused.
        $this->signUp('2026-08-05');

        $this->actingAs($this->parent)
            ->patch(route('weekly-plan.reset'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-05',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-05']);
    }

    public function test_staff_cannot_reset_a_sign_up_either(): void
    {
        // Giving up a place is a sign-up decision, not a plan one — /care is where it
        // belongs, and staff may withdraw there at any time.
        $this->signUp('2026-08-05');

        $this->actingAs($this->staff)
            ->patch(route('weekly-plan.reset'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-05',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-05']);
    }

    public function test_resetting_an_ordinary_override_still_works(): void
    {
        // The guard must not spill over to normal days.
        DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-08-12',
            'planned_time' => '14:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->parent)
            ->patch(route('weekly-plan.reset'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-12',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', ['date' => '2026-08-12']);
    }

    // --- un-offering a day ---------------------------------------------------------

    public function test_removing_an_offered_day_withdraws_its_sign_ups(): void
    {
        $this->signUp('2026-08-05');
        $this->signUp('2026-08-06');

        $this->actingAs($this->staff)
            ->delete(route('care-days.destroy', HolidayCareDay::firstWhere('date', '2026-08-05')))
            ->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', ['date' => '2026-08-05']);
        // The other day is untouched.
        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-06']);
    }

    public function test_deleting_the_period_withdraws_every_sign_up(): void
    {
        $this->signUp('2026-08-05');
        $this->signUp('2026-08-06');

        $this->actingAs($this->staff)
            ->delete(route('closures.destroy', $this->period))
            ->assertRedirect();

        // The day rows cascade in the database, which fires no model events — the
        // period deletes them itself so the sign-ups go too.
        $this->assertDatabaseEmpty('holiday_care_days');
        $this->assertDatabaseEmpty('daily_departures');
    }

    public function test_shrinking_the_range_withdraws_the_dropped_days(): void
    {
        $this->signUp('2026-08-07');

        $this->actingAs($this->staff)
            ->patch(route('closures.update', $this->period), [
                'name' => $this->period->name,
                'type' => 'care',
                'starts_on' => '2026-08-05',
                'ends_on' => '2026-08-06',
                'registration_deadline' => '2026-08-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', ['date' => '2026-08-07']);
    }

    public function test_a_day_already_lived_through_keeps_its_history(): void
    {
        $departed = $this->signUp('2026-08-05');
        $departed->update(['left_at' => now(), 'status' => DepartureStatus::PickedUp]);

        $this->actingAs($this->staff)
            ->delete(route('care-days.destroy', HolidayCareDay::firstWhere('date', '2026-08-05')))
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', ['date' => '2026-08-05']);
    }

    // --- a sign-up is not just „a row on that date" -------------------------------

    public function test_removing_a_day_spares_a_plan_override_from_before_the_period(): void
    {
        // The parent moved that day's pickup back when it was an ordinary Hort day;
        // publishing a Ferienbetreuung later doesn't make it a sign-up.
        $override = DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-08-06',
            'planned_time' => '14:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff)
            ->delete(route('care-days.destroy', HolidayCareDay::firstWhere('date', '2026-08-06')))
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', ['id' => $override->id]);
    }

    public function test_removing_a_day_spares_a_sign_up_from_another_period(): void
    {
        // Two Ferienbetreuungen may offer the same date — the unique index is per
        // period — so one un-offering its day must not cancel the other's places.
        $other = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-05',
            'ends_on' => '2026-08-05',
            'registration_deadline' => '2026-08-01',
        ]);
        $other->generateCareDays();

        $second = Child::factory()->create(['name' => 'Ben']);
        $otherSignUp = DailyDeparture::create([
            'child_id' => $second->id,
            'date' => '2026-08-05',
            'holiday_care_day_id' => $other->careDays()->first()->id,
            'planned_time' => '16:30',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);
        $mine = $this->signUp('2026-08-05');

        $this->actingAs($this->staff)
            ->delete(route('care-days.destroy', HolidayCareDay::firstWhere('holiday_period_id', $this->period->id)))
            ->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', ['id' => $mine->id]);
        $this->assertDatabaseHas('daily_departures', ['id' => $otherSignUp->id]);
    }

    public function test_an_old_override_is_not_shown_as_a_sign_up(): void
    {
        DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-08-06',
            'planned_time' => '14:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        // /care must not show the day pre-ticked …
        $this->actingAs($this->parent)
            ->get(route('care.index'))
            ->assertInertia(fn ($page) => $page->where('periods.0.days.1.children', []));

        // … the Wochenplan must call the child „nicht angemeldet" …
        $this->actingAs($this->parent)
            ->get(route('weekly-plan', ['week' => '2026-08-03']))
            ->assertInertia(fn ($page) => $page->where('currentWeek.0.days.3.care.registered', false));

        // … and the board must not list them as attending.
        $this->travelTo(Carbon::parse('2026-08-06 09:00'));
        $this->actingAs($this->staff)
            ->get(route('board'))
            ->assertInertia(fn ($page) => $page->has('rows', 0));
    }

    public function test_withdrawing_leaves_an_unrelated_override_alone(): void
    {
        $override = DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => '2026-08-06',
            'planned_time' => '14:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        // Staff save „no days" for this child (the deadline has passed, so only they can).
        $this->actingAs($this->staff)
            ->patch(route('care.update', $this->period), ['child_id' => $this->child->id, 'day_ids' => []])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', ['id' => $override->id]);
    }

    // --- a care day is still a normal Hort day ------------------------------------

    public function test_a_child_can_be_reported_sick_on_a_care_day(): void
    {
        $this->signUp('2026-08-05');

        $this->actingAs($this->parent)
            ->post(route('absences.store'), [
                'child_id' => $this->child->id,
                'from' => '2026-08-05',
                'to' => '2026-08-05',
                'reason' => AbsenceReason::Sick->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('absences', ['date' => '2026-08-05']);
        // Reporting a child away clears their pickup, care day or not.
        $this->assertDatabaseMissing('daily_departures', ['date' => '2026-08-05']);
    }

    public function test_a_late_change_on_a_care_day_notifies_staff(): void
    {
        Notification::fake();
        Setting::set(Setting::LateChangeCutoff, '12:00');
        $this->staff->forceFill(['slack_id' => 'U-STAFF'])->save();

        // Today is a care day for this test, and it's already afternoon.
        $today = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-03',
            'registration_deadline' => '2026-08-01',
        ]);
        $today->generateCareDays();
        $this->signUp('2026-08-03');
        $this->travelTo(Carbon::parse('2026-08-03 15:00'));

        $this->actingAs($this->parent)
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $this->child->id,
                'date' => '2026-08-03',
                'planned_time' => '16:00',
                'planned_method' => 'picked_up',
            ])
            ->assertRedirect();

        Notification::assertSentTo($this->staff, LateChange::class);
    }

    public function test_an_excursion_may_be_planned_on_a_care_day(): void
    {
        // Only a Schließzeit blocks a trip — Ferienbetreuung is when they happen most.
        $this->actingAs($this->staff)
            ->post(route('excursions.store'), [
                'name' => 'Zoo',
                'date' => '2026-08-05',
                'rsvp_deadline' => '2026-08-04',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('excursions', ['name' => 'Zoo']);
    }

    public function test_a_closure_on_the_same_day_wins_everywhere(): void
    {
        HolidayPeriod::factory()->onDay('2026-08-05')->create(['name' => 'Brückentag']);

        // Board: the closure card, no care banner (already covered) — here the
        // Wochenplan and the Tagesprogramm, which resolve the two independently.
        $this->actingAs($this->staff)
            ->get(route('weekly-plan'))
            ->assertInertia(fn ($page) => $page
                ->where('closedDays.2026-08-05', 'Brückentag')
                ->where('currentWeek.0.days.2.closed', 'Brückentag')
            );

        $this->actingAs($this->staff)
            ->get(route('program'))
            ->assertInertia(fn ($page) => $page->where('days.2.closed', 'Brückentag'));
    }

    // --- a Schließzeit inside a Ferienbetreuung -----------------------------------

    public function test_a_day_already_closed_is_never_offered(): void
    {
        HolidayPeriod::factory()->onDay('2026-09-09')->create(['name' => 'Brückentag']);

        $care = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-09-07',
            'ends_on' => '2026-09-11',
            'registration_deadline' => '2026-09-01',
        ]);
        $care->generateCareDays();

        $this->assertFalse(HolidayCareDay::query()->onDate('2026-09-09')->exists());
        // The rest of the week is offered as usual.
        $this->assertSame(4, $care->careDays()->count());
    }

    public function test_closing_a_day_afterwards_stops_offering_it_and_withdraws_the_sign_ups(): void
    {
        $care = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-09-07',
            'ends_on' => '2026-09-11',
            'registration_deadline' => '2026-09-01',
        ]);
        $care->generateCareDays();

        $wednesday = HolidayCareDay::query()->onDate('2026-09-09')->first();
        $this->actingAs($this->parent)
            ->patch(route('care.update', $care), ['child_id' => $this->child->id, 'day_ids' => [$wednesday->id]])
            ->assertRedirect();
        $this->assertDatabaseHas('daily_departures', ['date' => '2026-09-09']);

        // Staff then close that Wednesday — the Hort being shut is the stronger word.
        HolidayPeriod::factory()->onDay('2026-09-09')->create(['name' => 'Brückentag']);

        $this->assertFalse(HolidayCareDay::query()->onDate('2026-09-09')->exists());
        $this->assertDatabaseMissing('daily_departures', ['date' => '2026-09-09']);
        // Only that day: Thursday is still on offer.
        $this->assertTrue(HolidayCareDay::query()->onDate('2026-09-10')->exists());
    }

    public function test_moving_a_closure_onto_a_care_day_stops_offering_it(): void
    {
        $care = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-09-07',
            'ends_on' => '2026-09-11',
            'registration_deadline' => '2026-09-01',
        ]);
        $care->generateCareDays();

        $closure = HolidayPeriod::factory()->onDay('2026-09-14')->create(['name' => 'Brückentag']);

        $this->actingAs($this->staff)
            ->patch(route('closures.update', $closure), [
                'name' => 'Brückentag',
                'starts_on' => '2026-09-10',
                'ends_on' => '2026-09-10',
            ])
            ->assertRedirect();

        $this->assertFalse(HolidayCareDay::query()->onDate('2026-09-10')->exists());
    }

    public function test_re_saving_the_ferienbetreuung_restores_a_day_once_the_closure_is_gone(): void
    {
        $closure = HolidayPeriod::factory()->onDay('2026-09-09')->create();
        $care = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-09-07',
            'ends_on' => '2026-09-11',
            'registration_deadline' => '2026-09-01',
        ]);
        $care->generateCareDays();
        $this->assertFalse(HolidayCareDay::query()->onDate('2026-09-09')->exists());

        $closure->delete();

        // Deleting the closure doesn't bring the day back by itself — editing the
        // Ferienbetreuung re-offers any weekday that's missing.
        $this->actingAs($this->staff)
            ->patch(route('closures.update', $care), [
                'name' => $care->name,
                'type' => 'care',
                'starts_on' => '2026-09-07',
                'ends_on' => '2026-09-11',
                'registration_deadline' => '2026-09-01',
            ])
            ->assertRedirect();

        $this->assertTrue(HolidayCareDay::query()->onDate('2026-09-09')->exists());
    }

    public function test_signing_up_skips_a_day_closed_since_the_page_loaded(): void
    {
        $care = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-09-07',
            'ends_on' => '2026-09-11',
            'registration_deadline' => '2026-09-01',
        ]);
        $care->generateCareDays();
        $wednesday = HolidayCareDay::query()->onDate('2026-09-09')->first();

        // Closed straight in the database, so the offered day survives — the same race
        // as a parent holding the sign-up screen open while staff close a day.
        HolidayPeriod::factory()->onDay('2026-09-09')->createQuietly();

        $this->actingAs($this->parent)
            ->patch(route('care.update', $care), ['child_id' => $this->child->id, 'day_ids' => [$wednesday->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', ['date' => '2026-09-09']);
    }

    // --- a week that is part closed, part Ferienbetreuung -------------------------

    public function test_a_week_of_closures_and_care_days_wants_no_lunch(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-08-03 11:30')); // Monday

        // Mon–Wed shut, Thu–Fri Ferienbetreuung: nobody is there to cook on the closed
        // days, and lunch is optional on the care days. Nothing left to chase.
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-05')->create(['name' => 'Sommerferien']);
        $care = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-06',
            'ends_on' => '2026-08-07',
            'registration_deadline' => '2026-08-01',
        ]);
        $care->generateCareDays();

        $this->staff->forceFill(['slack_id' => 'U-STAFF'])->save();

        $this->assertSame([], DailyProgram::weekdaysWithoutLunch(Carbon::today()));

        $this->artisan('program:remind-missing')->assertSuccessful();
        Notification::assertNotSentTo($this->staff, ProgramMissing::class);
    }

    public function test_such_a_week_still_gets_its_wochenüberblick(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-08-03 11:30'));

        HolidayPeriod::factory()->between('2026-08-03', '2026-08-05')->create(['name' => 'Sommerferien']);
        $care = HolidayPeriod::factory()->care()->create([
            'starts_on' => '2026-08-06',
            'ends_on' => '2026-08-07',
            'registration_deadline' => '2026-08-01',
        ]);
        $care->generateCareDays();

        $this->parent->forceFill(['slack_id' => 'U-PARENT'])->save();

        // Only an entirely closed week is silent — here there are Betreuungszeiten to
        // report, so saying nothing would be worse than the message.
        $this->artisan('weekly:digest')->assertSuccessful();
        Notification::assertSentTo($this->parent, WeeklyDigest::class);
    }

    public function test_excursion_trumping_is_not_symmetric(): void
    {
        // A trip already booked stays bookable when a Ferienbetreuung is added later.
        Excursion::create(['name' => 'Zoo', 'date' => '2026-08-05', 'rsvp_deadline' => '2026-08-04']);

        $this->assertDatabaseHas('excursions', ['date' => '2026-08-05']);
        $this->assertTrue(HolidayCareDay::query()->onDate('2026-08-05')->exists());
    }
}
