<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\StandbyDuty;
use App\Models\StandbySwap;
use App\Models\User;
use App\Support\StandbyRoster;

// The Springerdienst page: the coordinator fills it (by hand or from the fair proposal),
// everyone else reads it with their own days highlighted.

/** Next month — every one of its days lies ahead, so all of them can be filled. */
function standbyNextMonth(): array
{
    $month = now()->addMonthNoOverflow()->startOfMonth();

    return [$month->format('Y-m'), array_key_first(StandbyRoster::slotsBetween($month, $month->copy()->endOfMonth()))];
}

it('lets the coordinator pick a child by hand and save it', function () {
    [$month, $firstDay] = standbyNextMonth();
    $child = Child::factory()->withGuardian(User::factory()->create())->create(['name' => 'Emma']);

    actAndVisit(User::factory()->standbyCoordinator()->create(), "/standby?month={$month}")
        ->select("@standby-slot-{$firstDay}-1", (string) $child->id)
        ->assertVisible('@standby-savebar')
        ->click('@standby-save')
        ->assertMissing('@standby-savebar');

    expect(StandbyDuty::sole())
        ->child_id->toBe($child->id)
        ->date->toDateString()->toBe($firstDay);
});

it('fills the month from the fair proposal once saved', function () {
    [$month] = standbyNextMonth();
    Child::factory()->count(3)->sequence(fn ($s) => ['name' => "Kind {$s->index}"])
        ->create()
        ->each(fn (Child $child) => $child->guardians()->attach(User::factory()->create()));

    $page = actAndVisit(User::factory()->standbyCoordinator()->create(), "/standby?month={$month}")
        ->click('@standby-fill')
        ->click('@fill-go')
        ->assertVisible('@standby-savebar');

    // A proposal is only a proposal until saved.
    expect(StandbyDuty::count())->toBe(0);

    $page->click('@standby-save')->assertMissing('@standby-savebar');

    $start = now()->addMonthNoOverflow()->startOfMonth();
    expect(StandbyDuty::count())->toBe(count(StandbyRoster::slotsBetween($start, $start->copy()->endOfMonth())));
});

it('leaves an unticked child out of the fair fill', function () {
    [$month] = standbyNextMonth();
    $children = Child::factory()->count(3)->sequence(fn ($s) => ['name' => "Kind {$s->index}"])
        ->create()
        ->each(fn (Child $child) => $child->guardians()->attach(User::factory()->create()));
    $vorstand = $children->first();

    actAndVisit(User::factory()->standbyCoordinator()->create(), "/standby?month={$month}")
        ->click('@standby-fill')
        ->click("@fill-child-{$vorstand->id}")
        ->click('@fill-go')
        ->assertVisible('@standby-savebar')
        ->click('@standby-save')
        ->assertMissing('@standby-savebar');

    expect(StandbyDuty::count())->toBeGreaterThan(0)
        ->and(StandbyDuty::where('child_id', $vorstand->id)->count())->toBe(0);
});

it('lets one family ask for a swap and the other accept it', function () {
    $month = now()->addMonthNoOverflow()->startOfMonth();
    $days = array_keys(StandbyRoster::slotsBetween($month, $month->copy()->endOfMonth()));
    [$parentA, $parentB] = [User::factory()->parent()->create(), User::factory()->parent()->create()];
    $emma = Child::factory()->withGuardian($parentA)->create(['name' => 'Emma']);
    $paul = Child::factory()->withGuardian($parentB)->create(['name' => 'Paul']);
    $dutyA = StandbyDuty::factory()->for($emma)->onDay($days[0])->create();
    $dutyB = StandbyDuty::factory()->for($paul)->onDay($days[1])->create();

    actAndVisit($parentA, "/standby?month={$month->format('Y-m')}")
        ->click("@standby-swap-{$days[1]}-1")
        ->click("@swap-offer-{$dutyA->id}")
        ->click('@swap-send')
        ->assertVisible('@standby-swaps-outgoing');

    $swapId = StandbySwap::sole()->id;

    actAndVisit($parentB, "/standby?month={$month->format('Y-m')}")
        ->assertSeeIn('@standby-swaps-incoming', 'Emma')
        ->click("@swap-accept-{$swapId}")
        ->assertMissing('@standby-swaps-incoming')
        ->assertSeeIn("@standby-slot-{$days[0]}-1", 'Paul');

    expect($dutyB->refresh()->child_id)->toBe($emma->id);
});

it('shows a parent the list read-only with their own day marked', function () {
    [$month, $firstDay] = standbyNextMonth();
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->withGuardian($parent)->create(['name' => 'Emma']);
    StandbyDuty::factory()->for($child)->onDay($firstDay)->create();

    actAndVisit($parent, "/standby?month={$month}")
        ->assertSeeIn("@standby-slot-{$firstDay}-1", 'Emma')
        ->assertSeeIn("@standby-slot-{$firstDay}-1", 'Ihr seid eingeteilt')
        ->assertMissing('@standby-fill');
});
