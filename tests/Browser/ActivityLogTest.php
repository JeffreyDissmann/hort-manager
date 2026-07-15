<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\User;

it('lets an admin open the activity log from the menu', function () {
    $admin = User::factory()->staff()->admin()->create();
    // A logged action to display.
    $this->actingAs($admin);
    Child::factory()->create(['name' => 'Protokoll Kind']);

    actAndVisit($admin, '/tagesboard')
        ->click('@user-menu')
        ->click('@nav-activity-log')
        ->assertPathIs('/protokoll')
        ->assertSee('Protokoll')
        ->assertSee('Protokoll Kind'); // the logged entry
});

it('has no activity-log link for non-admins', function () {
    $staff = User::factory()->staff()->create();

    actAndVisit($staff, '/tagesboard')
        ->click('@user-menu')
        ->assertMissing('@nav-activity-log');
});
