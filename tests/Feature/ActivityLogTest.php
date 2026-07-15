<?php

declare(strict_types=1);

use App\Enums\DepartureStatus;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
