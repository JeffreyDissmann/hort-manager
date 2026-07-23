<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\Excursion;
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

it('does not remind guardians of a child who has left', function () {
    Child::factory()->former('2025-12-31')->create(['name' => 'Weg']); // no Stammplan, left

    // The missing-Stammplan reminder only considers currently-enrolled children.
    expect(Child::withoutSchedule()->activeOn(now())->pluck('name'))->not->toContain('Weg');
});
