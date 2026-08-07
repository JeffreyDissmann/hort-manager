<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\Setting;
use App\Models\User;

// The notification settings page: the audience split and the links to /program,
// where the timings behind the two staff categories are configured.

it('shows staff only the staff categories, with no section headings', function () {
    $staff = User::factory()->staff()->create();

    actAndVisit($staff, '/notifications')
        ->assertSee('Späte Änderungen')
        ->assertSee('Wochenprogramm fehlt')
        ->assertDontSee('Abholungen')
        // One audience → the „Als Erzieher:in" heading would just be noise.
        ->assertMissing('@notifications-section-staff');
});

it('shows a parent only the guardian categories', function () {
    $parent = User::factory()->parent()->create();

    actAndVisit($parent, '/notifications')
        ->assertSee('Abholungen')
        ->assertSee('Wochenüberblick')
        ->assertDontSee('Späte Änderungen')
        ->assertMissing('@settings-link-late_change');
});

it('labels both sections for staff who are a guardian themselves', function () {
    $staff = User::factory()->staff()->create();
    $staff->children()->attach(Child::factory()->create());

    actAndVisit($staff, '/notifications')
        ->assertPresent('@notifications-section-guardian')
        ->assertPresent('@notifications-section-staff')
        ->assertSee('Abholungen')
        ->assertSee('Späte Änderungen');
});

it('says over the Slack column why it is switched off', function () {
    // Without a linked account every Slack toggle is dead. The note under the
    // table is a scroll away for staff, who get two sections above it.
    $parent = User::factory()->parent()->create(['slack_id' => null]);

    actAndVisit($parent, '/notifications')->assertSee('nicht verknüpft');
});

it('links the late-change toggle to its cutoff on the program page', function () {
    Setting::set(Setting::LateChangeCutoff, '13:15');
    $staff = User::factory()->staff()->create();

    $page = actAndVisit($staff, '/notifications')
        ->assertSee('Aktuell ab 13:15 Uhr')
        ->click('@settings-link-late_change')
        // „Späte Änderungen" is on both pages, so assert the picker instead.
        ->assertPresent('@late-change-cutoff-hour');

    // The hash is what makes the target card scroll itself into view.
    expect($page->script('window.location.hash'))->toBe('#late-change');
});

it('links the week-program toggle to the digest time on the program page', function () {
    Setting::set(Setting::WeeklyDigestTime, '16:00');
    $staff = User::factory()->staff()->create();

    // The reminder runs half an hour before the digest.
    $page = actAndVisit($staff, '/notifications')
        ->assertSee('Aktuell montags um 15:30 Uhr')
        ->click('@settings-link-program_missing')
        ->assertPresent('@weekly-digest-time-hour');

    expect($page->script('window.location.hash'))->toBe('#weekly-digest');
});
