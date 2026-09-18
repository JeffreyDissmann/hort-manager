<?php

declare(strict_types=1);

use App\Enums\DepartureMethod;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
use App\Models\User;

// The shared day-editor popup, driven from the board's „Hortfrei" pill.
it('requires a method and a time before saving', function () {
    $staff = User::factory()->staff()->create();
    // Comes on a different weekday → „Hortfrei" today, but has a plan → shown as a pill.
    $otherWeekday = (boardWeekday() % 5) + 1;
    $child = Child::factory()->scheduledOn($otherWeekday, '15:00')->create(['name' => 'Theo']);

    actAndVisit($staff, '/board')
        ->click("@hortfrei-pill-{$child->id}")
        ->assertDisabled('@save')            // empty plan → can't save
        ->select('@method', 'picked_up')
        ->assertDisabled('@save')            // method but no time
        ->select('@time-hour', '16')
        ->select('@time-minute', '00')
        ->assertEnabled('@save')             // complete
        ->click('@save')
        ->assertMissing('@save');            // dialog closes once the POST lands

    expect(DailyDeparture::where('child_id', $child->id)->whereDate('date', today())->value('planned_method'))
        ->toBe(DepartureMethod::PickedUp);
});

it('adds a „kommt später" arrival with a reason, shown on the board', function () {
    $staff = User::factory()->staff()->create();
    $otherWeekday = (boardWeekday() % 5) + 1;
    $child = Child::factory()->scheduledOn($otherWeekday, '15:00')->create(['name' => 'Theo']);

    actAndVisit($staff, '/board')
        ->click("@hortfrei-pill-{$child->id}")
        ->select('@method', 'picked_up')
        ->select('@time-hour', '16')
        ->select('@time-minute', '00')
        ->assertMissing('@arrival-section')   // folded away until asked for
        ->click('@arrival-toggle')
        ->select('@arrives-at-hour', '16')
        ->select('@arrives-at-minute', '30')
        ->assertDisabled('@save')             // arriving after the pickup can't be saved
        ->select('@arrives-at-hour', '14')
        ->type('@arrival-note', 'Arzttermin')
        ->assertEnabled('@save')
        ->click('@save')
        ->assertMissing('@save')
        ->assertVisible("@late-arrival-{$child->id}")
        // …and in the summary next to the absences, so a missing child reads as „on the way".
        ->assertVisible("@arriving-later-{$child->id}")
        ->assertSeeIn('@arriving-later', 'Theo')
        ->assertSee('Arzttermin')
        ->assertNoJavaScriptErrors();

    expect(DailyDeparture::where('child_id', $child->id)->whereDate('date', boardDate())->first())
        ->arrivalTime()->toBe('14:30')
        ->arrival_note->toBe('Arzttermin');
});

it('sets up a companion pickup („geht mit … mit")', function () {
    $staff = User::factory()->staff()->create();
    // Theo is „Hortfrei" today (scheduled another weekday) → editable via the pill.
    $otherWeekday = (boardWeekday() % 5) + 1;
    $theo = Child::factory()->scheduledOn($otherWeekday, '15:00')->create(['name' => 'Theo']);
    // Mia is here today → she's an eligible companion in the picker.
    $mia = Child::factory()->scheduledOn(boardWeekday(), '15:00')->create(['name' => 'Mia']);

    actAndVisit($staff, '/board')
        ->click("@hortfrei-pill-{$theo->id}")
        ->select('@method', 'with_child')
        ->select('@companion', (string) $mia->id)
        ->assertEnabled('@save')            // with_child needs a companion, not a time
        ->click('@save')
        ->assertMissing('@save');           // dialog closes once the POST lands

    expect(DailyDeparture::where('child_id', $theo->id)->whereDate('date', today())->first())
        ->planned_method->toBe(DepartureMethod::WithChild)
        ->companion_child_id->toBe($mia->id);
});

it('warns when the chosen pickup falls into the day\'s homework or activity', function () {
    $staff = User::factory()->staff()->create();
    $child = Child::factory()->scheduledOn(boardWeekday(), '17:00')->create(['name' => 'Theo']);
    HomeworkDefault::create(['weekday' => boardWeekday(), 'start_time' => '14:00', 'end_time' => '15:00']);
    DailyProgram::factory()->create([
        'date' => boardDate()->toDateString(), 'activity' => 'Fußballtraining',
        'activity_start' => '15:30', 'activity_end' => '16:30',
    ]);

    actAndVisit($staff, '/board')
        ->click("@edit-row-{$child->id}")
        ->assertMissing('@homework-clash')       // 17:00 is after both windows
        ->assertMissing('@activity-clash')
        ->select('@time-hour', '14')
        ->assertVisible('@homework-clash')       // 14:00 → homework time
        ->assertMissing('@activity-clash')
        ->select('@time-hour', '16')
        ->assertVisible('@activity-clash')       // 16:00 → mid-Fußballtraining
        ->assertSee('Fußballtraining')
        ->assertEnabled('@save')                 // a warning, not a block
        ->assertNoJavaScriptErrors();
});

it('warns when the chosen pickup falls inside the child\'s excursion', function () {
    $staff = User::factory()->staff()->create();
    $child = Child::factory()->scheduledOn(boardWeekday(), '17:00')->create(['name' => 'Theo']);
    // A trip Theo joins that day, back at 16:30 — 17:00 is fine, 15:00 is not.
    $trip = Excursion::factory()->create([
        'name' => 'Zoo', 'date' => boardDate()->toDateString(),
        'depart_at' => '13:30', 'return_at' => '16:30',
    ]);
    $trip->children()->syncWithoutDetaching([$child->id => ['response' => true]]);

    actAndVisit($staff, '/board')
        ->click("@edit-row-{$child->id}")
        ->assertMissing('@excursion-clash')     // 17:00 is after the trip
        ->select('@time-hour', '15')
        ->assertVisible('@excursion-clash')     // 15:00 is in the middle of it
        ->assertSee('bis 16:30')
        ->assertEnabled('@save')                // still allowed: pickup at the venue
        ->assertNoJavaScriptErrors();
});
