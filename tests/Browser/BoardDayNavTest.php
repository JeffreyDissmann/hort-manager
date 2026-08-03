<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\User;

// The Heute board's day selector — view any weekday, not just today.
it('previews a future day without live marking', function () {
    $staff = User::factory()->staff()->create();
    $future = boardDate()->addWeek(); // same weekday next week
    $child = Child::factory()->scheduledOn($future->isoWeekday(), '15:00')->create(['name' => 'Rosa Zukunft']);

    actAndVisit($staff, '/board?date='.$future->toDateString())
        ->assertSee('Rosa Zukunft')
        ->assertMissing("@mark-picked-up-{$child->id}") // no marking off a future day
        ->assertMissing("@absence-comment-{$child->id}"); // absence form stays collapsed (synthesized-row id fix)
});

it('opens a month calendar from the day label', function () {
    $staff = User::factory()->staff()->create();

    actAndVisit($staff, '/board')
        ->assertMissing('@date-picker')
        ->click('@day-picker-toggle')
        ->assertPresent('@date-picker'); // shared DatePicker popover (no native double-dropdown)
});

it('renders the day nav with a "back to today" link only when off today', function () {
    $staff = User::factory()->staff()->create();
    $future = boardDate()->addWeek();

    // On today: nav present, but no "Zu heute" link.
    actAndVisit($staff, '/board')
        ->assertPresent('@day-prev')
        ->assertPresent('@day-next')
        ->assertMissing('@day-today');

    // On a future day: the "Zu heute" link appears.
    actAndVisit($staff, '/board?date='.$future->toDateString())
        ->assertPresent('@day-today');
});
