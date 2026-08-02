<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\User;

// The Ferien page: Schließzeiten (staff manage, everyone reads).

it('lets staff enter a single closed day', function () {
    $staff = User::factory()->staff()->create();
    // A day inside the visible month, so one calendar click is enough.
    $day = now()->startOfMonth()->addDays(20)->toDateString();

    actAndVisit($staff, '/closures')
        ->type('@closure-name', 'Brückentag')
        ->click('#closure-from')
        ->click("@date-pick-{$day}")
        ->click('@closure-save')
        ->assertSee('Brückentag');

    // Picking „von" carries „bis" along, so a single day needs no second click.
    expect(HolidayPeriod::where('name', 'Brückentag')->first())
        ->starts_on->toDateString()->toBe($day)
        ->ends_on->toDateString()->toBe($day);
});

it('shows a parent the closures read-only', function () {
    $parent = User::factory()->parent()->create();
    $parent->children()->attach(Child::factory()->create());
    HolidayPeriod::factory()->between('2026-12-24', '2026-12-31')->create(['name' => 'Weihnachtsferien']);

    actAndVisit($parent, '/closures')
        ->assertSee('Weihnachtsferien')
        // No add form and no per-period actions for parents.
        ->assertMissing('@closure-name')
        ->assertMissing('@closure-save');
});

it('removes a closure after confirming', function () {
    $staff = User::factory()->staff()->create();
    $closure = HolidayPeriod::factory()->between('2026-12-24', '2026-12-31')->create(['name' => 'Weihnachtsferien']);

    actAndVisit($staff, '/closures')
        ->click("@closure-delete-{$closure->id}")
        ->click("@closure-delete-confirm-{$closure->id}")
        ->assertDontSee('Weihnachtsferien');

    expect(HolidayPeriod::count())->toBe(0);
});
