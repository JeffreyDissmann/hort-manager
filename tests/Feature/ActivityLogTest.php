<?php

declare(strict_types=1);

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HomeworkDefault;
use App\Models\User;
use App\Support\CompanionAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('shows the activity log to admins', function () {
    $admin = User::factory()->staff()->admin()->create();
    activity()->log('Testeintrag');

    $this->actingAs($admin)
        ->get('/protokoll')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ActivityLog/Index')
            ->has('activities.data'));
});

it('forbids non-admins from the activity log', function () {
    $staff = User::factory()->staff()->create(); // staff, but not an admin

    $this->actingAs($staff)->get('/protokoll')->assertForbidden();
});

it('records model changes as activity, with the causer and a label', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $child = Child::factory()->create(['name' => 'Protokoll Kind']);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => Child::class,
        'subject_id' => $child->id,
        'event' => 'created',
    ]);

    $entry = Activity::where('subject_type', Child::class)->where('subject_id', $child->id)->first();
    expect($entry->description)->toContain('Protokoll Kind')
        ->and($entry->causer_id)->toBe($admin->id);
});

it('records what changed when a day plan is adjusted (time + method)', function () {
    $staff = User::factory()->staff()->create();
    $child = Child::factory()->create();
    $date = boardDate()->toDateString();

    // An existing 15:00 pickup that the staff then changes.
    DailyDeparture::create([
        'child_id' => $child->id,
        'date' => $date,
        'status' => DepartureStatus::Present,
        'planned_time' => '15:00',
        'planned_method' => 'picked_up',
    ]);

    $this->actingAs($staff)
        ->patch('/wochenplan/anpassung', [
            'child_id' => $child->id,
            'date' => $date,
            'planned_method' => 'sent_home',
            'planned_time' => '16:00',
        ])
        ->assertRedirect();

    $entry = Activity::where('event', 'adjusted')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and(data_get($entry->attribute_changes, 'attributes.planned_time'))->toBe('16:00')
        ->and(data_get($entry->attribute_changes, 'old.planned_time'))->toBe('15:00')
        ->and(data_get($entry->attribute_changes, 'attributes.method'))->toBe('sent_home')
        ->and(data_get($entry->attribute_changes, 'old.method'))->toBe('picked_up');
});

it('does not log a day-plan adjustment that changes nothing', function () {
    $staff = User::factory()->staff()->create();
    $child = Child::factory()->create();
    $date = boardDate()->toDateString();

    DailyDeparture::create([
        'child_id' => $child->id,
        'date' => $date,
        'status' => DepartureStatus::Present,
        'planned_time' => '15:00',
        'planned_method' => 'picked_up',
    ]);

    // Re-save the exact same plan (a common no-op from the DayEditor).
    $this->actingAs($staff)
        ->patch('/wochenplan/anpassung', [
            'child_id' => $child->id,
            'date' => $date,
            'planned_method' => 'picked_up',
            'planned_time' => '15:00',
        ])
        ->assertRedirect();

    expect(Activity::where('event', 'adjusted')->count())->toBe(0);
});

it('logs a companion („geht mit … mit") answer', function () {
    Notification::fake();
    Bus::fake();

    $parent = User::factory()->create();
    $tom = Child::factory()->create(['name' => 'Tom']);
    $emma = Child::factory()->create(['name' => 'Emma']);
    $emma->guardians()->attach($parent);

    $departure = DailyDeparture::create([
        'child_id' => $tom->id,
        'date' => boardDate()->toDateString(),
        'planned_method' => DepartureMethod::WithChild,
        'companion_child_id' => $emma->id,
        'companion_confirmed' => null,
        'status' => DepartureStatus::Present,
    ]);

    CompanionAnswer::record($departure, true, $parent->id);

    expect(Activity::where('event', 'companion_yes')->where('causer_id', $parent->id)->count())->toBe(1);
});

it('logs when a reported absence is cleared', function () {
    $staff = User::factory()->staff()->create();
    $child = Child::factory()->create();
    $date = boardDate()->toDateString();
    Absence::report($child, $date, AbsenceReason::Sick, $staff->id, null);

    $this->actingAs($staff)
        ->delete('/abwesenheiten', ['child_id' => $child->id, 'from' => $date, 'to' => $date])
        ->assertRedirect();

    expect(Activity::where('subject_type', Absence::class)->where('event', 'deleted')->count())->toBe(1);
});

it('logs homework-default changes', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->patch('/programm/standard', ['defaults' => [['weekday' => 1, 'start' => '14:00', 'end' => '15:00']]])
        ->assertRedirect();

    expect(Activity::where('subject_type', HomeworkDefault::class)->where('event', 'created')->count())->toBe(1);
});
