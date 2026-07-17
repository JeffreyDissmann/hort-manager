<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;

// The Wochenplan is the DayEditor's *other* entry point (the board is the first).
it('adjusts a day from the Wochenplan and resets it back to the Stammplan', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->scheduledOn(boardWeekday(), '15:00')->withGuardian($parent)->create(['name' => 'Nina']);
    $date = boardDate()->toDateString();

    $page = actAndVisit($parent, "/wochenplan?week={$date}");

    // Adjust today's 15:00 pickup to 16:00 (opens with the Stammplan plan pre-filled).
    $page->click("@wp-cell-{$child->id}-{$date}")
        ->assertEnabled('@save')
        ->select('@time-hour', '16')
        ->click('@save')
        ->assertSee('16:00'); // only the adjusted cell shows 16:00

    expect((string) DailyDeparture::where('child_id', $child->id)->whereDate('date', $date)->value('planned_time'))
        ->toContain('16:00');

    // Reset the override — the day reverts to the Stammplan (15:00) and the row is gone.
    $page->click("@wp-cell-{$child->id}-{$date}")
        ->click('@reset')
        ->assertDontSee('16:00');

    expect(DailyDeparture::where('child_id', $child->id)->whereDate('date', $date)->exists())
        ->toBeFalse();
});

it('links each weekday header to that day\'s board', function () {
    $staff = User::factory()->staff()->create();
    Child::factory()->scheduledOn(boardWeekday(), '15:00')->create(['name' => 'Nils']);
    $date = boardDate()->toDateString();

    actAndVisit($staff, '/wochenplan')
        ->assertPresent("@wp-day-link-{$date}"); // the timetable header links to Heute for that date
});
