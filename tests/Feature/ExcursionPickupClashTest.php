<?php

declare(strict_types=1);

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;
use App\Notifications\LateChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

// A pickup between departure and return means nobody is at the Hort to hand the child
// over — so joining a trip moves that day's pickup to the trip's end by itself, and
// says so. Only that one day; the Stammplan and everything else about the day stay.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-06-22 09:00')); // Monday
    $this->parent = User::factory()->create(['role' => UserRole::Parent]);
    $this->child = Child::factory()->create(['name' => 'Nika']);
    $this->parent->children()->attach($this->child);
    $this->child->weeklySchedules()->create(['weekday' => 3, 'planned_time' => '14:00', 'method' => DepartureMethod::SentHome]);

    // Wednesday trip, 13:30–17:00 — the 14:00 pickup falls inside it.
    $this->excursion = Excursion::factory()->create([
        'name' => 'Reptilienauffangstation', 'date' => '2026-06-24',
        'depart_at' => '13:30', 'return_at' => '17:00',
    ]);
    $this->excursion->children()->syncWithoutDetaching([$this->child->id => ['response' => true]]);
});

/** @param  array<string, mixed>  $where */
function assertPlan(array $where): void
{
    test()->actingAs(test()->parent)->get('/polls')
        ->assertInertia(function (AssertableInertia $page) use ($where) {
            foreach ($where as $key => $value) {
                $page->where("upcoming.0.children.0.plan.{$key}", $value);
            }
        });
}

it('flags a pickup that falls inside the trip', function () {
    assertPlan(['time' => '14:00', 'conflict' => true, 'movable' => true, 'method' => 'sent_home']);
});

it('does not flag a pickup after the trip is back', function () {
    $this->child->weeklySchedules()->first()->update(['planned_time' => '17:30']);

    assertPlan(['time' => '17:30', 'conflict' => false]);
});

it('cannot judge a trip without a return time', function () {
    $this->excursion->update(['return_at' => null]);

    assertPlan(['conflict' => false]);
});

it('reports but does not offer to move a „geht mit … mit" pickup', function () {
    $mia = Child::factory()->create(['name' => 'Mia']);
    $mia->weeklySchedules()->create(['weekday' => 3, 'planned_time' => '14:00', 'method' => DepartureMethod::PickedUp]);
    DailyDeparture::create([
        'child_id' => $this->child->id, 'date' => '2026-06-24', 'status' => 'present',
        'planned_method' => DepartureMethod::WithChild, 'companion_child_id' => $mia->id,
        'companion_confirmed' => true,
    ]);

    // The time is mirrored from Mia, so the clash is real but moving it would break
    // the arrangement — the family is pointed at the Wochenplan instead.
    assertPlan(['time' => '14:00', 'conflict' => true, 'movable' => false]);
});

it('says nothing for a child reported absent that day', function () {
    Absence::report($this->child, '2026-06-24', AbsenceReason::Sick, $this->parent->id, 'Fieber');

    $this->actingAs($this->parent)->get('/polls')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('upcoming.0.children.0.plan', null));
});

it('moves the pickup to the trip\'s return when the family joins', function () {
    // The family already wrote a note and a „kommt später" for that day.
    $this->actingAs($this->parent)->patch(route('weekly-plan.adjust'), [
        'child_id' => $this->child->id, 'date' => '2026-06-24',
        'planned_time' => '14:00', 'planned_method' => 'sent_home', 'time_qualifier' => 'at',
        'note' => 'Schlüssel dabei', 'arrives_at' => '13:00', 'arrival_note' => 'Zahnarzt',
    ])->assertSessionHasNoErrors();

    // Answering „Ja" is all it takes — the pickup moves to the trip's return.
    $this->actingAs($this->parent)
        ->patch(route('polls.update', $this->excursion), ['child_id' => $this->child->id, 'response' => true])
        ->assertSessionHas('status', fn (string $s) => str_contains($s, '17:00') && str_contains($s, '14:00'));

    $day = DailyDeparture::firstWhere('child_id', $this->child->id);
    expect(substr((string) $day->planned_time, 0, 5))->toBe('17:00')
        // Everything else about the day survives the move.
        ->and($day->note)->toBe('Schlüssel dabei')
        ->and($day->arrivalTime())->toBe('13:00')
        ->and($day->planned_method)->toBe(DepartureMethod::SentHome);

    // That one day only — the Stammplan still says 14:00.
    expect(substr((string) $this->child->weeklySchedules()->first()->planned_time, 0, 5))->toBe('14:00');

    assertPlan(['time' => '17:00', 'conflict' => false]);
});

it('moves nothing when the child is not joining', function () {
    $this->actingAs($this->parent)
        ->patch(route('polls.update', $this->excursion), ['child_id' => $this->child->id, 'response' => false])
        ->assertSessionHas('status', __('flash.rsvp_saved', ['name' => 'Nika']));

    expect(DailyDeparture::count())->toBe(0);
});

it('leaves a „geht mit … mit" pickup alone and keeps warning about it', function () {
    $mia = Child::factory()->create(['name' => 'Mia']);
    $mia->weeklySchedules()->create(['weekday' => 3, 'planned_time' => '14:00', 'method' => DepartureMethod::PickedUp]);
    DailyDeparture::create([
        'child_id' => $this->child->id, 'date' => '2026-06-24', 'status' => 'present',
        'planned_method' => DepartureMethod::WithChild, 'companion_child_id' => $mia->id,
        'companion_confirmed' => true,
    ]);

    $this->actingAs($this->parent)
        ->patch(route('polls.update', $this->excursion), ['child_id' => $this->child->id, 'response' => true])
        ->assertSessionHas('status', __('flash.rsvp_saved', ['name' => 'Nika']));

    $day = DailyDeparture::firstWhere('child_id', $this->child->id);
    expect($day->planned_time)->toBeNull()
        ->and($day->planned_method)->toBe(DepartureMethod::WithChild);

    assertPlan(['conflict' => true, 'movable' => false]);
});

it('tells staff when a parent moves it late in the day', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-06-24 13:00')); // the trip day, after the cutoff
    $staff = User::factory()->create(['role' => UserRole::Staff, 'slack_id' => 'U-STAFF']);

    $this->actingAs($this->parent)
        ->patch(route('polls.update', $this->excursion), ['child_id' => $this->child->id, 'response' => true]);

    Notification::assertSentTo($staff, LateChange::class, fn (LateChange $n) => str_contains($n->summary, '17:00'));
});
