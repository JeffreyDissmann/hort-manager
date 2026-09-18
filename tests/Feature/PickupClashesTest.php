<?php

declare(strict_types=1);

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HolidayPeriod;
use App\Models\HomeworkDefault;
use App\Models\User;
use App\Support\PickupClashes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

// The standing summary above the page: every upcoming pickup that lands inside the
// Hausaufgabenzeit, a timed Aktivität or an Ausflug — plus the Stammplan colliding with
// the weekday's default homework slot, which repeats until the Stammplan changes.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-06-22 08:00')); // Monday
    $this->parent = User::factory()->create(['role' => UserRole::Parent]);
    $this->child = Child::factory()->create(['name' => 'Nina']);
    $this->parent->children()->attach($this->child);
});

/** Nina's Stammplan for a weekday (1 = Monday). */
function schedule(int $weekday, string $time, DepartureMethod $method = DepartureMethod::PickedUp): void
{
    test()->child->weeklySchedules()->create(['weekday' => $weekday, 'planned_time' => $time, 'method' => $method]);
}

it('reports the Stammplan colliding with the weekday homework default', function () {
    HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);
    schedule(1, '14:30');

    $clashes = PickupClashes::for($this->parent);

    expect($clashes['recurring'])->toHaveCount(1)
        ->and($clashes['recurring'][0])->toMatchArray([
            'child' => 'Nina', 'weekday' => 1, 'time' => '14:30', 'kind' => 'homework', 'from' => '14:00', 'to' => '15:00',
        ])
        // …and not once more for every Monday in the next two weeks.
        ->and($clashes['dated'])->toBe([]);
});

it('does not repeat the recurring finding once the board has seeded the day', function () {
    HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);
    schedule(1, '14:30');

    // Opening the board writes today's row from the Stammplan — that row says nothing
    // the „jeden Montag" line doesn't already say.
    $this->actingAs($this->parent)->get(route('board'))->assertOk();

    $clashes = PickupClashes::for($this->parent);

    expect($clashes['recurring'])->toHaveCount(1)
        ->and($clashes['dated'])->toBe([]);
});

it('says nothing when the Stammplan sits outside the homework slot', function () {
    HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);
    schedule(1, '15:00'); // the moment homework ends

    expect(PickupClashes::for($this->parent))->toBe(['recurring' => [], 'dated' => []]);
});

it('reports a day whose own plan runs into the homework slot', function () {
    HomeworkDefault::create(['weekday' => 2, 'start_time' => '14:00', 'end_time' => '15:00']);
    schedule(2, '16:00'); // the Stammplan itself is fine

    // …but this one Tuesday was moved into the homework slot.
    $this->actingAs($this->parent)->patch(route('weekly-plan.adjust'), [
        'child_id' => $this->child->id, 'date' => '2026-06-23',
        'planned_time' => '14:30', 'planned_method' => 'picked_up',
    ])->assertSessionHasNoErrors();

    $clashes = PickupClashes::for($this->parent);

    expect($clashes['recurring'])->toBe([])
        ->and($clashes['dated'])->toHaveCount(1)
        ->and($clashes['dated'][0])->toMatchArray(['date' => '2026-06-23', 'time' => '14:30', 'kind' => 'homework']);
});

it('reports a pickup inside a timed Aktivität', function () {
    schedule(3, '15:30');
    DailyProgram::factory()->create([
        'date' => '2026-06-24', 'activity' => 'Fußballtraining',
        'activity_start' => '15:00', 'activity_end' => '16:00',
    ]);

    expect(PickupClashes::for($this->parent)['dated'][0])->toMatchArray([
        'date' => '2026-06-24', 'kind' => 'activity', 'name' => 'Fußballtraining', 'from' => '15:00', 'to' => '16:00',
    ]);
});

it('reports a pickup inside an Ausflug the child joins', function () {
    schedule(4, '15:00');
    $trip = Excursion::factory()->create([
        'name' => 'Zoo', 'date' => '2026-06-25', 'depart_at' => '13:30', 'return_at' => '17:00',
    ]);
    $trip->children()->syncWithoutDetaching([$this->child->id => ['response' => true]]);

    expect(PickupClashes::for($this->parent)['dated'][0])->toMatchArray([
        'date' => '2026-06-25', 'kind' => 'excursion', 'name' => 'Zoo',
    ]);
});

it('ignores days the child is away, and days the Hort is shut', function () {
    HomeworkDefault::create(['weekday' => 2, 'start_time' => '14:00', 'end_time' => '15:00']);
    HomeworkDefault::create(['weekday' => 3, 'start_time' => '14:00', 'end_time' => '15:00']);
    schedule(2, '16:00');
    schedule(3, '16:00');

    // Both days are moved into the homework slot …
    foreach (['2026-06-23', '2026-06-24'] as $date) {
        $this->actingAs($this->parent)->patch(route('weekly-plan.adjust'), [
            'child_id' => $this->child->id, 'date' => $date,
            'planned_time' => '14:30', 'planned_method' => 'picked_up',
        ]);
    }

    // … but Nina is ill on the Tuesday, and the Wednesday is a Schließtag.
    Absence::report($this->child, '2026-06-23', AbsenceReason::Sick, $this->parent->id, 'Fieber');
    HolidayPeriod::create(['name' => 'Fortbildung', 'type' => 'closed', 'starts_on' => '2026-06-24', 'ends_on' => '2026-06-24']);

    expect(PickupClashes::for($this->parent)['dated'])->toBe([]);
});

it('tells staff nothing — they have the board for that', function () {
    HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);
    schedule(1, '14:30');
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $staff->children()->attach($this->child);

    expect(PickupClashes::for($staff))->toBe(['recurring' => [], 'dated' => []]);
});

it('ships the summary to the page as a shared prop', function () {
    HomeworkDefault::create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);
    schedule(1, '14:30');

    $this->actingAs($this->parent)->get(route('weekly-plan'))
        ->assertInertia(fn ($page) => $page->where('pickupClashes.recurring.0.child', 'Nina'));
});
