<?php

declare(strict_types=1);

use App\Models\Child;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopes children active on a date (start bound and leave bound)', function () {
    $current = Child::factory()->create(['active_from' => '2024-08-01', 'active_until' => null]);
    $left = Child::factory()->create(['active_from' => '2020-08-01', 'active_until' => '2025-07-31']);
    $future = Child::factory()->create(['active_from' => '2027-08-01', 'active_until' => null]);

    $ids = Child::activeOn('2026-05-10')->pluck('id');

    expect($ids)->toContain($current->id)      // enrolled, open-ended
        ->not->toContain($left->id)            // already left
        ->not->toContain($future->id);         // not yet started
});

it('scopes children active in a year by enrolment overlap', function () {
    $joinedMidYear = Child::factory()->create(['active_from' => '2025-09-01', 'active_until' => null]);
    $leftMidYear = Child::factory()->create(['active_from' => '2020-01-01', 'active_until' => '2025-03-15']);
    $before = Child::factory()->create(['active_from' => '2020-01-01', 'active_until' => '2024-12-31']);
    $after = Child::factory()->create(['active_from' => '2026-01-01', 'active_until' => null]);

    $ids = Child::activeInYear(2025)->pluck('id');

    expect($ids)->toContain($joinedMidYear->id) // started within 2025
        ->toContain($leftMidYear->id)           // left within 2025
        ->not->toContain($before->id)           // left before 2025
        ->not->toContain($after->id);           // joined after 2025
});

it('scopes children active anywhere within a date range (week overlap)', function () {
    $leavesMidWeek = Child::factory()->create(['active_from' => '2020-01-01', 'active_until' => '2026-05-06']);
    $joinsMidWeek = Child::factory()->create(['active_from' => '2026-05-08', 'active_until' => null]);
    $outside = Child::factory()->create(['active_from' => '2026-06-01', 'active_until' => null]);

    // Week Mon 2026-05-04 … Fri 2026-05-08.
    $ids = Child::activeBetween('2026-05-04', '2026-05-08')->pluck('id');

    expect($ids)->toContain($leavesMidWeek->id) // active early that week
        ->toContain($joinsMidWeek->id)          // active late that week
        ->not->toContain($outside->id);
});

it('exposes isActiveOn on the model', function () {
    $child = Child::factory()->create(['active_from' => '2024-08-01', 'active_until' => '2026-07-31']);

    expect($child->isActiveOn('2026-05-10'))->toBeTrue()
        ->and($child->isActiveOn('2026-08-01'))->toBeFalse()   // after leaving
        ->and($child->isActiveOn('2024-01-01'))->toBeFalse();  // before joining
});
