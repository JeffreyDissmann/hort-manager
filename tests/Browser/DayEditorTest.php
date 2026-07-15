<?php

declare(strict_types=1);

use App\Enums\DepartureMethod;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;

// The shared day-editor popup, driven from the board's „Hortfrei" pill.
it('requires a method and a time before saving', function () {
    $staff = User::factory()->staff()->create();
    // Comes on a different weekday → „Hortfrei" today, but has a plan → shown as a pill.
    $otherWeekday = (boardWeekday() % 5) + 1;
    $child = Child::factory()->scheduledOn($otherWeekday, '15:00')->create(['name' => 'Theo']);

    actAndVisit($staff, '/tagesboard')
        ->click("@hortfrei-pill-{$child->id}")
        ->assertDisabled('@save')            // empty plan → can't save
        ->select('@method', 'picked_up')
        ->assertDisabled('@save')            // method but no time
        ->select('@time-hour', '16')
        ->select('@time-minute', '00')
        ->assertEnabled('@save')             // complete
        ->click('@save');

    expect(DailyDeparture::where('child_id', $child->id)->whereDate('date', today())->value('planned_method'))
        ->toBe(DepartureMethod::PickedUp);
});

it('sets up a companion pickup („geht mit … mit")', function () {
    $staff = User::factory()->staff()->create();
    // Theo is „Hortfrei" today (scheduled another weekday) → editable via the pill.
    $otherWeekday = (boardWeekday() % 5) + 1;
    $theo = Child::factory()->scheduledOn($otherWeekday, '15:00')->create(['name' => 'Theo']);
    // Mia is here today → she's an eligible companion in the picker.
    $mia = Child::factory()->scheduledOn(boardWeekday(), '15:00')->create(['name' => 'Mia']);

    actAndVisit($staff, '/tagesboard')
        ->click("@hortfrei-pill-{$theo->id}")
        ->select('@method', 'with_child')
        ->select('@companion', (string) $mia->id)
        ->assertEnabled('@save')            // with_child needs a companion, not a time
        ->click('@save');

    expect(DailyDeparture::where('child_id', $theo->id)->whereDate('date', today())->first())
        ->planned_method->toBe(DepartureMethod::WithChild)
        ->companion_child_id->toBe($mia->id);
});
