<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\Excursion;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('keeps a child who has left off the Tagesboard', function () {
    $this->travelTo(Carbon::parse('2026-06-22')); // Monday

    Child::factory()->scheduledOn(1, '16:00')->create(['name' => 'Aktiv']);
    Child::factory()->former('2025-12-31')->scheduledOn(1, '16:00')->create(['name' => 'Weg']); // has a Stammplan but left

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('board'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Aktiv'));
});

it('keeps a former child out of the weekly plan', function () {
    $this->travelTo(Carbon::parse('2026-06-22')); // Monday

    Child::factory()->scheduledOn(1, '16:00')->create(['name' => 'Aktiv']);
    Child::factory()->former('2025-12-31')->scheduledOn(1, '16:00')->create(['name' => 'Weg']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('weekly-plan'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('children', fn ($children) => collect($children)->pluck('name')->doesntContain('Weg')));
});

it('invites only children enrolled on the excursion date', function () {
    $active = Child::factory()->create(['name' => 'Aktiv']);
    Child::factory()->former('2025-12-31')->create(['name' => 'Weg']);

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('excursions.store'), [
            'name' => 'Zoo',
            'date' => '2026-06-19',
            'rsvp_deadline' => '2026-06-12',
        ])->assertRedirect();

    $excursion = Excursion::first();

    expect($excursion->children()->count())->toBe(1)
        ->and($excursion->children->first()->id)->toBe($active->id);
});

it('invites a child who joins after the excursion was created', function () {
    // The family used to see „2 von 5 dabei" with their own child missing and no way
    // to answer — the invite list was frozen at creation time.
    Excursion::factory()->create(['name' => 'Zoo', 'date' => today()->addWeek()->toDateString()]);
    Excursion::factory()->create(['name' => 'Waldtag', 'date' => today()->subWeek()->toDateString()]);

    $child = Child::factory()->create(['name' => 'Neu']);

    $zoo = Excursion::where('name', 'Zoo')->first();
    $past = Excursion::where('name', 'Waldtag')->first();

    expect($zoo->children()->pluck('children.id')->all())->toBe([$child->id])
        // …and their answer is still open, not a silent yes.
        ->and($zoo->children()->first()->pivot->response)->toBeNull()
        // A trip that has already happened is history — nobody joins it afterwards.
        ->and($past->children()->count())->toBe(0);
});

it('puts a child who joins later on an open Ferienbetreuung sheet by itself', function () {
    // No counterpart to the Ausflug fix is needed: a trip stores its invitations in a
    // pivot (a snapshot of who existed), a Ferienbetreuung derives its sheet from who
    // is enrolled. This pins that asymmetry so the sheet can't quietly become a snapshot.
    $parent = User::factory()->parent()->create();
    $period = HolidayPeriod::factory()->care()->create([
        'starts_on' => today()->addWeek()->toDateString(),
        'ends_on' => today()->addWeek()->addDays(2)->toDateString(),
        'registration_deadline' => today()->addDays(3)->toDateString(),
    ]);
    $period->generateCareDays();

    $child = Child::factory()->withGuardian($parent)->create(['name' => 'Neu']);

    $this->actingAs($parent)
        ->get(route('polls.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('care.periods.0.child_ids', [$child->id])
            // …and they count as „noch nicht beantwortet", so the badge chases them.
            ->where('care.periods.0.answered', [])
        );
});

it('does not invite a child whose enrolment starts after the trip', function () {
    Excursion::factory()->create(['name' => 'Zoo', 'date' => today()->addWeek()->toDateString()]);

    Child::factory()->create(['name' => 'Später', 'active_from' => today()->addMonth()->toDateString()]);

    expect(Excursion::where('name', 'Zoo')->first()->children()->count())->toBe(0);
});

it('does not remind guardians of a child who has left', function () {
    Child::factory()->former('2025-12-31')->create(['name' => 'Weg']); // no Stammplan, left

    // The missing-Stammplan reminder only considers currently-enrolled children.
    expect(Child::withoutSchedule()->activeOn(now())->pluck('name'))->not->toContain('Weg');
});

it('flags each child as active or former on the roster', function () {
    Child::factory()->create(['name' => 'Aktiv']);
    Child::factory()->former('2025-12-31')->create(['name' => 'Weg']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('children.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Children/Index')
            ->has('children', 2)
            ->where('children', fn ($children) => collect($children)
                ->firstWhere('name', 'Aktiv')['active'] === true
                && collect($children)->firstWhere('name', 'Weg')['active'] === false
                && collect($children)->firstWhere('name', 'Weg')['active_until'] === '2025-12-31'));
});
