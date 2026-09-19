<?php

declare(strict_types=1);

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Support\PickupClashes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// The Inertia middleware shares pickupClashes, pendingCare & co. with *every* request,
// so their cost is paid on every page a family opens. These tests pin the query count
// to the number of children and open Ferienbetreuungen instead of multiplying by them.

uses(RefreshDatabase::class);

/** Queries run while loading a page as this user. */
function queriesFor(User $user, string $route): int
{
    return countQueries(fn () => test()->actingAs($user)->get(route($route))->assertOk());
}

/** Queries run by one piece of work. */
function countQueries(callable $work): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    $work();

    return $count;
}

it('does not grow with the number of companion pickups', function () {
    $this->travelTo(Carbon::parse('2026-06-22 08:00')); // Monday
    $parent = User::factory()->create(['role' => UserRole::Parent]);

    // Each child goes home with someone else on a different day — resolving the
    // companion's mirrored time used to cost an EffectivePlan::for (4 queries) per row.
    $addCompanionPickup = function (int $i) use ($parent): void {
        $child = Child::factory()->create(['name' => "Kind {$i}"]);
        $parent->children()->attach($child);

        $companion = Child::factory()->create(['name' => "Begleitung {$i}"]);
        $companion->weeklySchedules()->create([
            'weekday' => $i, 'planned_time' => '14:30', 'method' => DepartureMethod::PickedUp,
        ]);

        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => Carbon::parse('2026-06-22')->addDays($i - 1)->toDateString(),
            'status' => DepartureStatus::Present,
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $companion->id,
            'companion_confirmed' => true,
        ]);
    };

    $addCompanionPickup(1);
    $one = countQueries(fn () => PickupClashes::for($parent));

    $addCompanionPickup(2);
    $addCompanionPickup(3);
    $addCompanionPickup(4);

    // Four times the arrangements, same number of queries: one batch for all of them.
    expect(countQueries(fn () => PickupClashes::for($parent)))->toBe($one);
});

it('seeds the board in one statement, whatever the group size', function () {
    $this->travelTo(Carbon::parse('2026-06-22 08:00')); // Monday
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $addScheduledChild = function (int $i): void {
        Child::factory()->create(['name' => "Kind {$i}"])
            ->weeklySchedules()->create([
                'weekday' => 1, 'planned_time' => '15:00', 'method' => DepartureMethod::PickedUp,
            ]);
    };

    $addScheduledChild(1);
    $one = queriesFor($staff, 'board');

    DailyDeparture::query()->delete(); // seed from scratch again
    foreach (range(2, 12) as $i) {
        $addScheduledChild($i);
    }

    // Twelve children instead of one, and the board still seeds with a single upsert
    // rather than a firstOrCreate each — this GET is opened all morning.
    expect(queriesFor($staff, 'board'))->toBe($one);
    expect(DailyDeparture::count())->toBe(12);
});

it('does not grow with the number of open Ferienbetreuungen', function () {
    $this->travelTo(Carbon::parse('2026-06-22 08:00'));
    $parent = User::factory()->create(['role' => UserRole::Parent]);
    $parent->children()->attach(Child::factory()->create(['name' => 'Nina']));

    HolidayPeriod::factory()->care()->create([
        'starts_on' => '2026-08-03', 'ends_on' => '2026-08-07', 'registration_deadline' => '2026-07-20',
    ]);

    $one = queriesFor($parent, 'board');

    foreach (range(1, 4) as $i) {
        HolidayPeriod::factory()->care()->create([
            'starts_on' => Carbon::parse('2026-09-07')->addWeeks($i)->toDateString(),
            'ends_on' => Carbon::parse('2026-09-11')->addWeeks($i)->toDateString(),
            'registration_deadline' => '2026-08-20',
        ]);
    }

    // Four more open sign-ups, and the middleware still asks the same questions.
    expect(queriesFor($parent, 'board'))->toBe($one);
});
