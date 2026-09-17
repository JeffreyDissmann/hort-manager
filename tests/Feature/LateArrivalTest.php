<?php

declare(strict_types=1);

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;
use App\Notifications\LateChange;
use App\Support\WeeklyDigestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

// „Kommt später": an optional, day-only arrival time + reason on the day plan. Nobody
// marks the arrival — it is information for staff, shown wherever the day is shown.

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    // Monday morning, before the 12:00 late-change cutoff.
    $this->travelTo(Carbon::parse('2026-06-22 09:00'));

    $this->parent = User::factory()->create(['role' => UserRole::Parent]);
    $this->child = Child::factory()->create(['name' => 'Tom']);
    $this->parent->children()->attach($this->child);
    $this->child->weeklySchedules()->create(['weekday' => 1, 'planned_time' => '16:00', 'method' => DepartureMethod::PickedUp]);
});

/** @param  array<string, mixed>  $overrides */
function adjustTom(array $overrides = [])
{
    return test()->actingAs(test()->parent)->patch(route('weekly-plan.adjust'), array_merge([
        'child_id' => test()->child->id,
        'date' => '2026-06-22',
        'planned_time' => '16:00',
        'planned_method' => 'picked_up',
        'arrives_at' => '14:30',
        'arrival_note' => 'Arzttermin',
    ], $overrides));
}

it('stores the arrival time and reason on the day', function () {
    adjustTom()->assertSessionHasNoErrors()->assertRedirect();

    $day = DailyDeparture::firstWhere('child_id', $this->child->id);
    expect($day->arrivalTime())->toBe('14:30')
        ->and($day->arrival_note)->toBe('Arzttermin');
});

it('drops the reason when no arrival time is given', function () {
    adjustTom(['arrives_at' => null, 'arrival_note' => 'Arzttermin'])->assertSessionHasNoErrors();

    $day = DailyDeparture::firstWhere('child_id', $this->child->id);
    expect($day->arrives_at)->toBeNull()->and($day->arrival_note)->toBeNull();
});

it('rejects an arrival at or after the pickup time', function () {
    adjustTom(['arrives_at' => '16:00'])->assertSessionHasErrors('arrives_at');

    expect(DailyDeparture::count())->toBe(0);
});

it('rejects an arrival after the companion\'s mirrored pickup', function () {
    $mia = Child::factory()->create(['name' => 'Mia']);
    $mia->weeklySchedules()->create(['weekday' => 1, 'planned_time' => '15:00', 'method' => DepartureMethod::PickedUp]);

    // „geht mit … mit" carries no own time — it mirrors Mia's 15:00.
    adjustTom(['planned_method' => 'with_child', 'companion_child_id' => $mia->id, 'planned_time' => null, 'arrives_at' => '15:30'])
        ->assertSessionHasErrors('arrives_at');

    adjustTom(['planned_method' => 'with_child', 'companion_child_id' => $mia->id, 'planned_time' => null, 'arrives_at' => '14:00'])
        ->assertSessionHasNoErrors();

    expect(DailyDeparture::firstWhere('child_id', $this->child->id)->arrivalTime())->toBe('14:00');
});

it('drops the arrival from a Wochenplan cell once the child is reported absent', function () {
    // A care-day sign-up survives an absence (it is the child's place), so a row — and
    // its arrival — can outlive the plan. The cell must show one state, not both.
    Absence::report($this->child, '2026-06-22', AbsenceReason::Sick, $this->parent->id, 'Fieber');
    DailyDeparture::create([
        'child_id' => $this->child->id, 'date' => '2026-06-22', 'status' => 'present',
        'planned_time' => '16:00', 'planned_method' => DepartureMethod::PickedUp,
        'arrives_at' => '14:30', 'arrival_note' => 'Arzttermin',
    ]);

    $this->actingAs($this->parent)->get(route('weekly-plan', ['week' => '2026-06-22']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentWeek.0.days.0.arrives_at', null)
            ->where('currentWeek.0.days.0.absent.reason', 'sick'));
});

it('counts a late arrival alone as a change of the day (logged)', function () {
    adjustTom(); // same pickup as the Stammplan, only the arrival is new

    $entry = Activity::where('event', 'adjusted')->latest('id')->first();
    expect($entry)->not->toBeNull()
        ->and(data_get($entry->attribute_changes, 'attributes.arrives_at'))->toBe('14:30')
        ->and(data_get($entry->attribute_changes, 'attributes.arrival_note'))->toBe('Arzttermin');
});

it('shows the late arrival on the board and in the Wochenplan (grid + timeline)', function () {
    adjustTom();

    $this->actingAs($this->parent)->get('/board')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.arrives_at', '14:30')
            ->where('rows.0.arrival_note', 'Arzttermin'));

    $this->actingAs($this->parent)->get(route('weekly-plan', ['week' => '2026-06-22']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentWeek.0.days.0.arrives_at', '14:30')
            ->where('currentWeek.0.days.0.arrival_note', 'Arzttermin'));
});

it('tells staff about a late arrival entered after the cutoff', function () {
    $this->travelTo(Carbon::parse('2026-06-22 13:00'));
    $staff = User::factory()->create(['role' => UserRole::Staff, 'slack_id' => 'U-STAFF']);

    adjustTom();

    Notification::assertSentTo($staff, LateChange::class, fn (LateChange $n) => str_contains($n->summary, 'kommt erst um 14:30 (Arzttermin)'));
});

it('mentions the late arrival in the Wochenüberblick', function () {
    adjustTom();

    $digest = WeeklyDigestBuilder::for($this->parent, Carbon::parse('2026-06-22'));

    expect($digest['children'][0]['days'][0]['summary'])->toContain('16:00')
        ->toContain('kommt erst um 14:30 (Arzttermin)');
});

it('carries the late arrival in the TRMNL feed', function () {
    adjustTom();

    $this->getJson(URL::signedRoute('trmnl.dashboard'))
        ->assertOk()
        ->assertJsonPath('today.departures.0.children.0.arrival', 'kommt erst um 14:30 (Arzttermin)');
});
