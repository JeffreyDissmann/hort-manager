<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;
use Illuminate\Support\Carbon;

// A parent answering the excursion participation poll through the UI.
it('lets a parent answer the poll for their child', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->withGuardian($parent)->create(['name' => 'Lena']);

    $excursion = Excursion::factory()->pollOpen()->create(['name' => 'Zoo-Ausflug']);
    $excursion->children()->attach($child->id); // invited, still open

    actAndVisit($parent, '/polls')
        ->assertSee('Zoo-Ausflug')
        ->click("@rsvp-yes-{$child->id}")
        ->assertSee('✓'); // the row flips to the „zugesagt ✓" state

    $this->assertDatabaseHas('child_excursion', [
        'child_id' => $child->id,
        'response' => true,
    ]);
});

it('lets a parent decline the poll for their child', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->withGuardian($parent)->create(['name' => 'Jonas']);

    $excursion = Excursion::factory()->pollOpen()->create(['name' => 'Museums-Ausflug']);
    $excursion->children()->attach($child->id);

    actAndVisit($parent, '/polls')
        ->assertSee('Museums-Ausflug')
        ->click("@rsvp-no-{$child->id}")
        ->assertSee('abgesagt'); // the row flips to the declined state

    $this->assertDatabaseHas('child_excursion', [
        'child_id' => $child->id,
        'response' => false,
    ]);
});

it('moves a pickup that falls inside the trip when the family joins', function () {
    $parent = User::factory()->parent()->create();
    $trip = Excursion::factory()->pollOpen()->create([
        'name' => 'Zoo-Ausflug', 'depart_at' => '13:30', 'return_at' => '17:00',
    ]);
    // Scheduled at 14:00 on the trip's weekday — right in the middle of it.
    $weekday = Carbon::parse($trip->date)->isoWeekday();
    $child = Child::factory()->scheduledOn($weekday, '14:00')->withGuardian($parent)->create(['name' => 'Nika']);
    // (ChildObserver already invites a fresh child to every upcoming trip.)

    actAndVisit($parent, '/polls')
        ->assertMissing("@pickup-at-return-{$trip->id}-{$child->id}")
        ->click("@rsvp-yes-{$child->id}")
        // Said plainly: moved, that day only.
        ->assertSee('steht jetzt auf 17:00 Uhr')
        ->assertVisible("@pickup-at-return-{$trip->id}-{$child->id}")
        ->assertMissing("@pickup-clash-{$trip->id}-{$child->id}")
        ->assertNoJavaScriptErrors();

    expect(substr((string) DailyDeparture::where('child_id', $child->id)->whereDate('date', $trip->date)->value('planned_time'), 0, 5))
        ->toBe('17:00');
    // That one day only — the Stammplan is untouched.
    expect(substr((string) $child->weeklySchedules()->first()->planned_time, 0, 5))->toBe('14:00');
});
