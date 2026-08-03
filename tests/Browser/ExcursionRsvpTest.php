<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\Excursion;
use App\Models\User;

// A parent answering the excursion participation poll through the UI.
it('lets a parent answer the poll for their child', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->withGuardian($parent)->create(['name' => 'Lena']);

    $excursion = Excursion::factory()->pollOpen()->create(['name' => 'Zoo-Ausflug']);
    $excursion->children()->attach($child->id); // invited, still open

    actAndVisit($parent, '/polls')
        ->assertSee('Zoo-Ausflug')
        ->click("@rsvp-yes-{$child->id}")
        ->assertSee('✓'); // the row flips to the „zugesagt ✓" state

    $this->assertDatabaseHas('child_excursion', [
        'child_id' => $child->id,
        'response' => true,
    ]);
});

it('lets a parent decline the poll for their child', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->withGuardian($parent)->create(['name' => 'Jonas']);

    $excursion = Excursion::factory()->pollOpen()->create(['name' => 'Museums-Ausflug']);
    $excursion->children()->attach($child->id);

    actAndVisit($parent, '/polls')
        ->assertSee('Museums-Ausflug')
        ->click("@rsvp-no-{$child->id}")
        ->assertSee('abgesagt'); // the row flips to the declined state

    $this->assertDatabaseHas('child_excursion', [
        'child_id' => $child->id,
        'response' => false,
    ]);
});
