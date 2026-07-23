<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Jobs\AskCompanionConfirmation;
use App\Jobs\SyncCompanionConfirmation;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Notifications\CompanionAnswered;
use App\Notifications\CompanionCancelled;
use App\Notifications\CompanionRequest;
use App\Support\CompanionReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompanionDepartureTest extends TestCase
{
    use RefreshDatabase;

    /** Wednesday of the current (frozen) week — today or later, so it's editable. */
    private function wednesday(): string
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22')); // Monday

        return '2026-06-24';
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    /** Give a child a concrete same-day pickup so EffectivePlan resolves it. */
    private function departsAt(Child $child, string $date, DepartureMethod $method): void
    {
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $date,
            'planned_time' => '15:00',
            'planned_method' => $method,
            'status' => DepartureStatus::Present,
        ]);
    }

    public function test_going_with_a_picked_up_child_is_auto_confirmed(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        $this->departsAt($tom, $date, DepartureMethod::PickedUp);

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $anna->id,
                'date' => $date,
                'planned_method' => DepartureMethod::WithChild->value,
                'companion_child_id' => $tom->id,
            ])
            ->assertRedirect();

        // No adult-less situation → confirmed straight away, no request sent.
        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => 'with_child',
            'companion_child_id' => $tom->id,
            'companion_confirmed' => true,
        ]);
        Notification::assertNothingSent();
    }

    public function test_going_with_a_lone_child_stays_pending_and_notifies(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        $this->departsAt($tom, $date, DepartureMethod::SentHome);

        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $anna->id,
                'date' => $date,
                'planned_method' => DepartureMethod::WithChild->value,
                'companion_child_id' => $tom->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $anna->id,
            'companion_child_id' => $tom->id,
            'companion_confirmed' => null,
        ]);
        Notification::assertSentTo($tomsParent, CompanionRequest::class);
    }

    public function test_a_companion_inherits_the_lone_childs_time_qualifier(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        // Tom geht allein „ab 15:00".
        DailyDeparture::create([
            'child_id' => $tom->id,
            'date' => $date,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::SentHome,
            'time_qualifier' => TimeQualifier::From,
            'status' => DepartureStatus::Present,
        ]);
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);
        $staff = $this->staff();

        $this->actingAs($staff)->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ])->assertRedirect();

        // The weekly grid mirrors Tom's „ab" qualifier onto Anna's day (even pending).
        $this->actingAs($staff)->get(route('weekly-plan'))
            ->assertInertia(fn ($page) => $page
                ->where('currentWeek.0.name', 'Anna')
                ->where('currentWeek.0.days.2.time', '15:00')
                ->where('currentWeek.0.days.2.qualifier', TimeQualifier::From->value)
                ->where('currentWeek.0.days.2.companion.name', 'Tom'));

        // Once confirmed, the board shows the mirrored time with the „ab" prefix.
        $departure = DailyDeparture::where('child_id', $anna->id)->firstOrFail();
        $this->actingAs($tomsParent)->patch(route('companion.confirm', $departure), ['confirmed' => true]);

        $this->actingAs($staff)->get(route('board', ['date' => $date]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.name', 'Anna')
                ->where('rows.0.planned_method', 'with_child')
                ->where('rows.0.planned_time', '15:00')
                ->where('rows.0.qualifier_prefix', TimeQualifier::From->prefix()));
    }

    public function test_a_companion_who_goes_with_a_third_child_is_rejected(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $lea = Child::factory()->create();
        // Tom himself tags along with Lea → Anna may not chain onto Tom.
        DailyDeparture::create([
            'child_id' => $tom->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $lea->id,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $anna->id,
                'date' => $date,
                'planned_method' => DepartureMethod::WithChild->value,
                'companion_child_id' => $tom->id,
            ])
            ->assertSessionHasErrors('companion_child_id');

        $this->assertDatabaseMissing('daily_departures', ['child_id' => $anna->id]);
    }

    public function test_an_absent_companion_is_rejected(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $this->departsAt($tom, $date, DepartureMethod::SentHome);
        Absence::create(['child_id' => $tom->id, 'date' => $date, 'reason' => 'sick']);

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $anna->id,
                'date' => $date,
                'planned_method' => DepartureMethod::WithChild->value,
                'companion_child_id' => $tom->id,
            ])
            ->assertSessionHasErrors('companion_child_id');
    }

    public function test_the_companion_guardian_can_confirm_and_the_requester_is_told(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);
        $annasParent = User::factory()->create(['role' => UserRole::Parent]);
        $annasParent->children()->attach($anna);

        $departure = DailyDeparture::create([
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $tom->id,
            'companion_confirmed' => null,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($tomsParent)
            ->patch(route('companion.confirm', $departure), ['confirmed' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'id' => $departure->id,
            'companion_confirmed' => true,
            'companion_confirmed_by' => $tomsParent->id,
        ]);
        // The requesting family learns the outcome.
        Notification::assertSentTo($annasParent, CompanionAnswered::class);
    }

    public function test_a_companion_switching_to_alone_reopens_the_arrangement(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);

        // Tom is picked up → Anna's arrangement is auto-approved.
        $this->departsAt($tom, $date, DepartureMethod::PickedUp);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('daily_departures', ['child_id' => $anna->id, 'companion_confirmed' => true]);

        // Tom's family switches Tom to going home alone → Anna's arrangement reopens.
        $this->actingAs($tomsParent)->patch(route('weekly-plan.adjust'), [
            'child_id' => $tom->id,
            'date' => $date,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::SentHome->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('daily_departures', ['child_id' => $anna->id, 'companion_confirmed' => null]);
        Notification::assertSentTo($tomsParent, CompanionRequest::class);
    }

    public function test_the_board_hides_an_unconfirmed_arrangement_as_a_pickup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24')); // a Wednesday (weekday 3)
        $date = '2026-06-24';
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        WeeklySchedule::create(['child_id' => $anna->id, 'weekday' => 3, 'planned_time' => '14:00', 'method' => DepartureMethod::PickedUp]);
        WeeklySchedule::create(['child_id' => $tom->id, 'weekday' => 3, 'planned_time' => '15:00', 'method' => DepartureMethod::SentHome]);
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);
        $staff = $this->staff();

        // Anna → geht mit Tom mit; Tom goes alone, so it's pending.
        $this->actingAs($staff)->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ])->assertRedirect();

        // Pending → the board sees a normal pickup at Tom's synced time, no companion.
        $this->actingAs($staff)->get(route('board'))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.name', 'Anna')
                ->where('rows.0.planned_method', 'picked_up')
                ->where('rows.0.planned_time', '15:00')
                ->where('rows.0.companion', null));

        // Once confirmed, the arrangement becomes visible.
        $departure = DailyDeparture::where('child_id', $anna->id)->firstOrFail();
        $this->actingAs($tomsParent)->patch(route('companion.confirm', $departure), ['confirmed' => true]);

        $this->actingAs($staff)->get(route('board'))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.name', 'Anna')
                ->where('rows.0.planned_method', 'with_child')
                ->where('rows.0.companion.name', 'Tom'));
    }

    public function test_a_board_override_of_the_companion_reconciles_the_arrangement(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-24')); // a Wednesday (weekday 3)
        $date = '2026-06-24';
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        WeeklySchedule::create(['child_id' => $tom->id, 'weekday' => 3, 'planned_time' => '15:00', 'method' => DepartureMethod::SentHome]);
        $staff = $this->staff();

        // Anna → geht mit Tom mit; Tom goes home alone, so the arrangement is pending.
        $this->actingAs($staff)->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ])->assertRedirect();

        $anna->refresh();
        $this->assertNull(DailyDeparture::where('child_id', $anna->id)->firstOrFail()->companion_confirmed);

        // Load the board so Tom's row is seeded, then override it to „picked up".
        $this->actingAs($staff)->get(route('board'));
        $tomDeparture = DailyDeparture::where('child_id', $tom->id)->where('date', $date)->firstOrFail();

        $this->actingAs($staff)->patch(route('board.override', $tomDeparture), [
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp->value,
        ])->assertRedirect();

        // Tom is now picked up → Anna needs no confirmation; the arrangement auto-confirms
        // (system, i.e. no human confirmer) via the board's reconcile call.
        $arrangement = DailyDeparture::where('child_id', $anna->id)->firstOrFail();
        $this->assertTrue($arrangement->companion_confirmed);
        $this->assertNull($arrangement->companion_confirmed_by);
    }

    public function test_a_stranger_cannot_confirm(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $departure = DailyDeparture::create([
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $tom->id,
            'status' => DepartureStatus::Present,
        ]);

        // A parent unrelated to Tom (the companion) may not answer.
        $stranger = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($stranger)
            ->patch(route('companion.confirm', $departure), ['confirmed' => true])
            ->assertForbidden();
    }

    public function test_confirming_a_normal_pickup_is_not_found(): void
    {
        $date = $this->wednesday();
        $child = Child::factory()->create();
        $departure = DailyDeparture::create([
            'child_id' => $child->id,
            'date' => $date,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);

        // Not a companion arrangement → 404 (and existence isn't leaked as a 403).
        $this->actingAs($this->staff())
            ->patch(route('companion.confirm', $departure), ['confirmed' => true])
            ->assertNotFound();
    }

    public function test_declining_tells_the_requester_and_records_the_no(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);
        $annasParent = User::factory()->create(['role' => UserRole::Parent]);
        $annasParent->children()->attach($anna);

        $departure = DailyDeparture::create([
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $tom->id,
            'companion_confirmed' => null,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($tomsParent)
            ->patch(route('companion.confirm', $departure), ['confirmed' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'id' => $departure->id,
            'companion_confirmed' => false,
            'companion_confirmed_by' => $tomsParent->id,
        ]);
        Notification::assertSentTo($annasParent, CompanionAnswered::class);
    }

    public function test_staff_can_confirm_on_a_familys_behalf(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $staff = $this->staff();
        $departure = DailyDeparture::create([
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $tom->id,
            'companion_confirmed' => null,
            'status' => DepartureStatus::Present,
        ]);

        $this->actingAs($staff)
            ->patch(route('companion.confirm', $departure), ['confirmed' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_departures', [
            'id' => $departure->id,
            'companion_confirmed' => true,
            'companion_confirmed_by' => $staff->id,
        ]);
    }

    public function test_a_child_cannot_go_with_itself(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create();

        $this->actingAs($this->staff())
            ->patch(route('weekly-plan.adjust'), [
                'child_id' => $anna->id,
                'date' => $date,
                'planned_method' => DepartureMethod::WithChild->value,
                'companion_child_id' => $anna->id,
            ])
            ->assertSessionHasErrors('companion_child_id');
    }

    public function test_a_companion_switching_back_to_pickup_auto_confirms(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);

        // Tom goes alone → Anna's arrangement is pending.
        $this->departsAt($tom, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('daily_departures', ['child_id' => $anna->id, 'companion_confirmed' => null]);

        // Tom's family switches Tom to being picked up → no gate needed, auto-confirmed.
        $this->actingAs($tomsParent)->patch(route('weekly-plan.adjust'), [
            'child_id' => $tom->id,
            'date' => $date,
            'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('daily_departures', ['child_id' => $anna->id, 'companion_confirmed' => true]);
    }

    public function test_a_human_confirmation_survives_a_replan_without_re_notifying(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);

        // Tom alone → Anna pending → the family confirms once.
        $this->departsAt($tom, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ]);
        $departure = DailyDeparture::where('child_id', $anna->id)->firstOrFail();
        $this->actingAs($tomsParent)->patch(route('companion.confirm', $departure), ['confirmed' => true]);

        // Tom re-plans (still alone). The human Yes stands; nobody is asked again.
        $this->actingAs($tomsParent)->patch(route('weekly-plan.adjust'), [
            'child_id' => $tom->id,
            'date' => $date,
            'planned_time' => '15:30',
            'planned_method' => DepartureMethod::SentHome->value,
        ]);

        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $anna->id,
            'companion_confirmed' => true,
            'companion_confirmed_by' => $tomsParent->id,
        ]);
        // Only the one request at creation — no duplicate on the re-plan.
        Notification::assertSentToTimes($tomsParent, CompanionRequest::class, 1);
    }

    public function test_reporting_the_companion_absent_unwinds_dependents(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        $annasParent = User::factory()->create(['role' => UserRole::Parent]);
        $annasParent->children()->attach($anna);

        // Anna is set to go home with Tom.
        $this->departsAt($tom, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('daily_departures', ['child_id' => $anna->id, 'companion_child_id' => $tom->id]);

        // Tom is reported away → Anna can't go with him. Her arrangement is unwound
        // (reverted to her Stammplan) and her family is told to re-plan.
        $this->actingAs($this->staff())->post(route('absences.store'), [
            'child_id' => $tom->id,
            'from' => $date,
            'to' => $date,
            'reason' => 'sick',
            'comment' => 'Fieber',
        ])->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', ['child_id' => $anna->id]);
        Notification::assertSentTo($annasParent, CompanionCancelled::class);
    }

    public function test_the_companion_family_sees_a_pending_count(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);

        $this->departsAt($tom, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ]);

        // The shared banner count reaches Tom's family.
        $this->actingAs($tomsParent)->get(route('board'))
            ->assertInertia(fn ($page) => $page->where('pendingCompanions', 1));
    }

    public function test_two_children_cannot_go_home_with_each_other(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $bob = Child::factory()->create();
        // Bob leaves on his own → a valid companion.
        $this->departsAt($bob, $date, DepartureMethod::SentHome);

        // Anna goes with Bob.
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $bob->id,
        ])->assertRedirect();

        // Bob can't now go with Anna — she's already a tag-along (no chains, no mutual).
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $bob->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $anna->id,
        ])->assertSessionHasErrors('companion_child_id');

        // Anna's arrangement stands; Bob was not turned into a tag-along.
        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $anna->id,
            'planned_method' => 'with_child',
            'companion_child_id' => $bob->id,
        ]);
        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $bob->id,
            'planned_method' => 'sent_home',
            'companion_child_id' => null,
        ]);
    }

    public function test_a_mutual_arrangement_self_heals_on_reconcile(): void
    {
        // A concurrent race is the only way two mutual rows could ever coexist. The
        // reconciler that runs after every plan write collapses it back to one.
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create(['name' => 'Anna']);
        $bob = Child::factory()->create(['name' => 'Bob']);
        $annasParent = User::factory()->create(['role' => UserRole::Parent]);
        $annasParent->children()->attach($anna);

        DailyDeparture::create([
            'child_id' => $anna->id, 'date' => $date, 'status' => DepartureStatus::Present,
            'planned_method' => DepartureMethod::WithChild, 'companion_child_id' => $bob->id, 'companion_confirmed' => true,
        ]);
        DailyDeparture::create([
            'child_id' => $bob->id, 'date' => $date, 'status' => DepartureStatus::Present,
            'planned_method' => DepartureMethod::WithChild, 'companion_child_id' => $anna->id, 'companion_confirmed' => true,
        ]);

        CompanionReconciler::reconcile($bob->id, $date);

        // Bob is a tag-along, so nobody can go with him → Anna is unwound + told.
        $this->assertDatabaseMissing('daily_departures', ['child_id' => $anna->id]);
        Notification::assertSentTo($annasParent, CompanionCancelled::class);
    }

    public function test_a_pending_arrangement_queues_the_slack_ask(): void
    {
        Bus::fake([AskCompanionConfirmation::class]);
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $this->departsAt($tom, $date, DepartureMethod::SentHome);

        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ]);

        Bus::assertDispatched(AskCompanionConfirmation::class);
    }

    public function test_answering_queues_the_slack_sync(): void
    {
        Bus::fake([SyncCompanionConfirmation::class]);
        $date = $this->wednesday();
        $anna = Child::factory()->create();
        $tom = Child::factory()->create();
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);
        $departure = DailyDeparture::create([
            'child_id' => $anna->id, 'date' => $date, 'status' => DepartureStatus::Present,
            'planned_method' => DepartureMethod::WithChild, 'companion_child_id' => $tom->id, 'companion_confirmed' => null,
        ]);

        $this->actingAs($tomsParent)->patch(route('companion.confirm', $departure), ['confirmed' => true]);

        Bus::assertDispatched(SyncCompanionConfirmation::class);
    }

    public function test_a_companion_becoming_a_tagalong_unwinds_dependents(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create(['name' => 'Anna']);
        $bob = Child::factory()->create(['name' => 'Bob']);
        $cara = Child::factory()->create(['name' => 'Cara']);
        $annasParent = User::factory()->create(['role' => UserRole::Parent]);
        $annasParent->children()->attach($anna);

        // Cara is picked up (a valid companion later); Bob goes alone; Anna goes with Bob.
        $this->departsAt($cara, $date, DepartureMethod::PickedUp);
        $this->departsAt($bob, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $bob->id,
        ])->assertRedirect();

        // Bob himself now tags along with Cara → a chain. Anna can't go with Bob anymore,
        // so her arrangement is unwound and her family told to re-plan.
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $bob->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $cara->id,
        ])->assertRedirect();

        $this->assertDatabaseMissing('daily_departures', ['child_id' => $anna->id]);
        Notification::assertSentTo($annasParent, CompanionCancelled::class);
    }

    public function test_deleting_a_companion_unwinds_dependents(): void
    {
        Notification::fake();
        $date = $this->wednesday();
        $anna = Child::factory()->create(['name' => 'Anna']);
        $bob = Child::factory()->create(['name' => 'Bob']);
        $annasParent = User::factory()->create(['role' => UserRole::Parent]);
        $annasParent->children()->attach($anna);

        $this->departsAt($bob, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $bob->id,
        ])->assertRedirect();

        // Bob is removed entirely → nobody can go with him.
        $bob->delete();

        $this->assertDatabaseMissing('daily_departures', ['child_id' => $anna->id]);
        Notification::assertSentTo($annasParent, CompanionCancelled::class);
    }

    public function test_the_requesting_parent_gets_an_informational_note(): void
    {
        $date = $this->wednesday();
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        $annasParent = User::factory()->create(['role' => UserRole::Parent]);
        $annasParent->children()->attach($anna);

        $this->departsAt($tom, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ]);

        // Anna's family sees the note as status only — they can't confirm (not Tom's).
        $this->actingAs($annasParent)->get(route('weekly-plan'))
            ->assertInertia(fn ($page) => $page
                ->where('companionNotes.0.child', 'Anna')
                ->where('companionNotes.0.companion', 'Tom')
                ->where('companionNotes.0.status', 'pending')
                ->where('companionNotes.0.actionable', false));
    }

    public function test_the_companion_family_gets_an_actionable_note(): void
    {
        // The board shows *today*, so make the arrangement fall on today.
        Carbon::setTestNow(Carbon::parse('2026-06-24')); // a Wednesday
        $date = '2026-06-24';
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        $tomsParent = User::factory()->create(['role' => UserRole::Parent]);
        $tomsParent->children()->attach($tom);

        $this->departsAt($tom, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ]);

        // Tom's family (the companion's guardian) gets the note as actionable — on Heute too.
        $this->actingAs($tomsParent)->get(route('board'))
            ->assertInertia(fn ($page) => $page
                ->where('companionNotes.0.companion', 'Tom')
                ->where('companionNotes.0.status', 'pending')
                ->where('companionNotes.0.actionable', true));
    }

    public function test_the_requesting_parent_sees_their_pending_arrangement(): void
    {
        $date = $this->wednesday(); // 2026-06-24 = Wednesday, day index 2
        $anna = Child::factory()->create(['name' => 'Anna']);
        $tom = Child::factory()->create(['name' => 'Tom']);
        $annasParent = User::factory()->create(['role' => UserRole::Parent]);
        $annasParent->children()->attach($anna);

        $this->departsAt($tom, $date, DepartureMethod::SentHome);
        $this->actingAs($this->staff())->patch(route('weekly-plan.adjust'), [
            'child_id' => $anna->id,
            'date' => $date,
            'planned_method' => DepartureMethod::WithChild->value,
            'companion_child_id' => $tom->id,
        ]);

        // The requester still sees the pending arrangement on their own child's card.
        $this->actingAs($annasParent)->get(route('weekly-plan'))
            ->assertInertia(fn ($page) => $page
                ->where('currentWeek.0.name', 'Anna')
                ->where('currentWeek.0.days.2.companion.name', 'Tom')
                ->where('currentWeek.0.days.2.companion.confirmed', null));
    }
}
