<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;

// The DayEditor's „die Erzieher:innen werden informiert" hint. It compares the
// *browser's* clock against the cutoff, so these drive it through the setting
// (00:00 = always past, 23:59 = never past) rather than by moving time.

beforeEach(function () {
    // The hint compares the *browser's* clock to the cutoff, so it only fires when the
    // board's day really is today. On a weekend the board rolls to the next weekday
    // (and tests/Pest.php freezes Carbon to it), so the dates can't line up — note the
    // real clock, not now(), which that freeze has already moved.
    if (Carbon::createFromTimestamp(time())->toDateString() !== boardDate()->toDateString()) {
        test()->markTestSkipped('The board targets the next weekday on weekends; the hint is same-day only.');
    }
});

it('warns a parent that staff will be told about a late change', function () {
    Setting::set(Setting::LateChangeCutoff, '00:00');

    $parent = User::factory()->parent()->create();
    $child = Child::factory()->scheduledOn(boardWeekday(), '15:00')->create(['name' => 'Mia']);
    $parent->children()->attach($child);

    actAndVisit($parent, '/board')
        ->click("@edit-row-{$child->id}")
        ->assertVisible('@late-change-hint')
        ->assertSee('Die Erzieher:innen werden über diese Änderung für heute benachrichtigt.');
});

it('stays quiet before the cutoff', function () {
    Setting::set(Setting::LateChangeCutoff, '23:59');

    $parent = User::factory()->parent()->create();
    $child = Child::factory()->scheduledOn(boardWeekday(), '15:00')->create(['name' => 'Mia']);
    $parent->children()->attach($child);

    actAndVisit($parent, '/board')
        ->click("@edit-row-{$child->id}")
        ->assertPresent('@save')          // the editor is open …
        ->assertMissing('@late-change-hint'); // … but nothing is late yet
})->skip(fn (): bool => now()->format('H:i') >= '23:59', 'Only fails in the last minute of the day.');

it('never warns staff, who are the ones being notified', function () {
    Setting::set(Setting::LateChangeCutoff, '00:00');

    $staff = User::factory()->staff()->create();
    $child = Child::factory()->scheduledOn(boardWeekday(), '15:00')->create(['name' => 'Mia']);

    actAndVisit($staff, '/board')
        ->click("@edit-row-{$child->id}")
        ->assertPresent('@save')
        ->assertMissing('@late-change-hint');
});
