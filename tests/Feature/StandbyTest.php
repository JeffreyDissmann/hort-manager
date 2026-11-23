<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\StandbyDuty;
use App\Models\User;
use App\Support\StandbyFill;
use App\Support\StandbyRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    // Friday before a March that starts on a Monday — every date below is ahead.
    $this->travelTo(Carbon::parse('2027-02-26 09:00'));
    $this->coordinator = User::factory()->standbyCoordinator()->create();
});

/** A child with a guardian of its own (one family each). */
function standbyChild(?User $guardian = null): Child
{
    return Child::factory()->withGuardian($guardian ?? User::factory()->create())->create();
}

describe('slots', function () {
    it('gives a weekday one slot and a weekend none', function () {
        expect(StandbyRoster::slotsBetween('2027-03-05', '2027-03-08'))
            ->toBe(['2027-03-05' => 1, '2027-03-08' => 1]);
    });

    it('gives a Schließzeit no slot', function () {
        HolidayPeriod::factory()->onDay('2027-03-03')->create();

        expect(StandbyRoster::slotsBetween('2027-03-01', '2027-03-05'))->not->toHaveKey('2027-03-03');
    });

    it('gives a Ferienbetreuung day two slots and a day it does not offer none', function () {
        $period = HolidayPeriod::factory()->care()->between('2027-03-01', '2027-03-05')->create();
        $period->generateCareDays();
        $period->careDays()->whereDate('date', '2027-03-03')->first()->delete();

        expect(StandbyRoster::slotsBetween('2027-03-01', '2027-03-05'))
            ->toBe(['2027-03-01' => 2, '2027-03-02' => 2, '2027-03-04' => 2, '2027-03-05' => 2]);
    });
});

describe('page', function () {
    it('shows everyone the list with their own days marked', function () {
        $parent = User::factory()->create();
        $own = standbyChild($parent);
        $other = standbyChild();
        StandbyDuty::factory()->for($own)->onDay('2027-03-01')->create();
        StandbyDuty::factory()->for($other)->onDay('2027-03-02')->create();

        $this->actingAs($parent)
            ->get('/standby?month=2027-03')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Standby/Index')
                ->where('canManage', false)
                ->where('children', [])
                ->where('days.0.date', '2027-03-01')
                ->where('days.0.slots.0.child.id', $own->id)
                ->where('days.0.slots.0.own', true)
                ->where('days.1.slots.0.own', false)
                ->where('days.2.slots.0.child', null)
                ->has('days', 23));
    });

    it('shows a date held by a child who has left as open, naming them', function () {
        $left = Child::factory()->former('2027-03-01')->withGuardian(User::factory()->create())->create(['name' => 'Emma']);
        StandbyDuty::factory()->for($left)->onDay('2027-03-01')->create(); // still enrolled that day
        StandbyDuty::factory()->for($left)->onDay('2027-03-02')->create(); // left by then

        $this->actingAs(User::factory()->create())
            ->get('/standby?month=2027-03')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('days.0.slots.0.child.id', $left->id)
                ->where('days.0.slots.0.former', null)
                ->where('days.1.slots.0.child', null)
                ->where('days.1.slots.0.former', 'Emma'));
    });

    it('does not propose to a parent', function () {
        standbyChild();

        $this->actingAs(User::factory()->create())
            ->get('/standby?month=2027-03&propose=1')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('proposal', null));
    });

    it('lets the coordinator see the eligible children with their count', function () {
        $child = standbyChild();
        Child::factory()->create(); // no guardian → nobody to stand by
        StandbyDuty::factory()->for($child)->onDay('2026-10-05')->create(); // same school year

        $this->actingAs($this->coordinator)
            ->get('/standby?month=2027-03')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canManage', true)
                ->has('children', 1)
                ->where("counts.{$child->id}", 1));
    });
});

describe('saving', function () {
    it('lets the coordinator assign, change and clear slots in one go', function () {
        [$a, $b] = [standbyChild(), standbyChild()];
        StandbyDuty::factory()->for($a)->onDay('2027-03-02')->create();
        StandbyDuty::factory()->for($a)->onDay('2027-03-03')->create();

        $this->actingAs($this->coordinator)
            ->put('/standby', ['assignments' => [
                ['date' => '2027-03-01', 'slot' => 1, 'child_id' => $a->id],
                ['date' => '2027-03-02', 'slot' => 1, 'child_id' => $b->id],
                ['date' => '2027-03-03', 'slot' => 1, 'child_id' => null],
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(StandbyDuty::orderBy('date')->get()->map(fn ($d) => [$d->date->toDateString(), $d->child_id])->all())
            ->toBe([['2027-03-01', $a->id], ['2027-03-02', $b->id]])
            ->and(StandbyDuty::first()->assigned_by)->toBe($this->coordinator->id);
    });

    it('lets an admin fill the list too', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/standby', ['assignments' => [['date' => '2027-03-01', 'slot' => 1, 'child_id' => standbyChild()->id]]])
            ->assertRedirect();

        expect(StandbyDuty::count())->toBe(1);
    });

    it('refuses parents and staff without the flag', function (string $state) {
        $user = User::factory()->{$state}()->create();

        $this->actingAs($user)
            ->put('/standby', ['assignments' => [['date' => '2027-03-01', 'slot' => 1, 'child_id' => standbyChild()->id]]])
            ->assertForbidden();
    })->with(['parent' => 'parent', 'staff' => 'staff']);

    it('rejects a slot the calendar does not have', function (string $date, int $slot) {
        $this->actingAs($this->coordinator)
            ->put('/standby', ['assignments' => [['date' => $date, 'slot' => $slot, 'child_id' => standbyChild()->id]]])
            ->assertSessionHasErrors('assignments.0.slot');

        expect(StandbyDuty::count())->toBe(0);
    })->with([
        'weekend' => ['2027-03-06', 1],
        'second slot on a normal day' => ['2027-03-01', 2],
    ]);

    it('rejects a child not enrolled on that day', function () {
        $left = Child::factory()->former('2027-02-28')->withGuardian(User::factory()->create())->create();

        $this->actingAs($this->coordinator)
            ->put('/standby', ['assignments' => [['date' => '2027-03-01', 'slot' => 1, 'child_id' => $left->id]]])
            ->assertSessionHasErrors('assignments.0.child_id');
    });
});

describe('fair fill', function () {
    it('fills only the empty slots and saves nothing', function () {
        $children = collect(range(1, 4))->map(fn () => standbyChild());
        StandbyDuty::factory()->for($children[0])->onDay('2027-03-02')->create();

        $proposal = app(StandbyFill::class)->propose(Carbon::parse('2027-03-01'), Carbon::parse('2027-03-05'));

        expect(collect($proposal)->pluck('date')->all())->toBe(['2027-03-01', '2027-03-03', '2027-03-04', '2027-03-05'])
            ->and(StandbyDuty::count())->toBe(1);
    });

    it('refills a date held by a child who has left', function () {
        $left = Child::factory()->former('2027-03-01')->withGuardian(User::factory()->create())->create();
        StandbyDuty::factory()->for($left)->onDay('2027-03-02')->create();
        $stays = standbyChild();

        $proposal = app(StandbyFill::class)->propose(Carbon::parse('2027-03-02'), Carbon::parse('2027-03-02'));

        expect($proposal)->toBe([['date' => '2027-03-02', 'slot' => 1, 'child_id' => $stays->id]]);
    });

    it('spreads the days evenly, starting with whoever was on least', function () {
        $busy = standbyChild();
        // Earlier this school year: $busy already had four duties.
        foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'] as $date) {
            StandbyDuty::factory()->for($busy)->onDay($date)->create();
        }
        // Last school year doesn't count.
        StandbyDuty::factory()->for($rested = standbyChild())->onDay('2026-06-01')->create();
        $others = collect(range(1, 4))->map(fn () => standbyChild());

        $proposal = collect(app(StandbyFill::class)->propose(Carbon::parse('2027-03-01'), Carbon::parse('2027-03-12')));

        // 10 Hort days over the 5 rested children: two each, and $busy, four ahead,
        // gets nothing until the others catch up.
        expect($proposal)->toHaveCount(10)
            ->and($proposal->where('child_id', $busy->id))->toHaveCount(0);
        foreach ([$rested, ...$others] as $child) {
            expect($proposal->where('child_id', $child->id))->toHaveCount(2);
        }
    });

    it('never puts one family on both slots of a Ferienbetreuung day', function () {
        $period = HolidayPeriod::factory()->care()->between('2027-03-01', '2027-03-05')->create();
        $period->generateCareDays();
        $parent = User::factory()->create();
        [$sibling1, $sibling2] = [standbyChild($parent), standbyChild($parent)];
        standbyChild();
        standbyChild();

        $proposal = collect(app(StandbyFill::class)->propose(Carbon::parse('2027-03-01'), Carbon::parse('2027-03-05')));

        expect($proposal)->toHaveCount(10);
        foreach ($proposal->groupBy('date') as $date => $day) {
            expect($day->pluck('child_id')->intersect([$sibling1->id, $sibling2->id])->count())->toBeLessThanOrEqual(1, "on {$date}");
        }
    });

    it('avoids the same family on consecutive Hort days when others are free', function () {
        $children = collect(range(1, 3))->map(fn () => standbyChild());

        $proposal = collect(app(StandbyFill::class)->propose(Carbon::parse('2027-03-01'), Carbon::parse('2027-03-12')))->values();

        foreach ($proposal as $i => $entry) {
            if ($i > 0) {
                expect($entry['child_id'])->not->toBe($proposal[$i - 1]['child_id']);
            }
        }
    });

    it('is offered to the coordinator for the month ahead only', function () {
        standbyChild();

        $this->actingAs($this->coordinator)
            ->get('/standby?month=2027-03&propose=1')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('proposal', 23));

        $this->actingAs($this->coordinator)
            ->get('/standby?month=2027-01&propose=1')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('proposal', null));
    });
});

it('lets an admin grant the coordinator flag without touching the other axes', function () {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch("/admin/users/{$user->id}", ['role' => 'parent', 'is_admin' => false, 'accounting_access' => 'none', 'manages_standby' => true])
        ->assertRedirect();
    expect($user->refresh()->manages_standby)->toBeTrue();

    // An older client that doesn't send the flag leaves it alone.
    $this->actingAs(User::factory()->admin()->create())
        ->patch("/admin/users/{$user->id}", ['role' => 'parent', 'is_admin' => false, 'accounting_access' => 'none']);
    expect($user->refresh()->manages_standby)->toBeTrue();
});
