<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\User;

it('lets an admin switch into Verwaltung and open the activity log', function () {
    $admin = User::factory()->staff()->admin()->create();
    // A logged action to display.
    $this->actingAs($admin);
    Child::factory()->create(['name' => 'Protokoll Kind']);

    actAndVisit($admin, '/board')
        // The wordmark is the way between worlds; Verwaltung opens on Statistik.
        ->click('@world-switch')
        ->click('@world-admin')
        ->assertPathIs('/admin/statistics')
        ->click('@user-menu')
        ->click('@nav-activity-log')
        ->assertPathIs('/admin/activity-log')
        ->assertSee('Protokoll')
        ->assertSee('Protokoll Kind'); // the logged entry
});

it('offers no Verwaltung world to non-admins', function () {
    $staff = User::factory()->staff()->create();

    actAndVisit($staff, '/board')
        ->click('@user-menu')
        ->assertMissing('@nav-activity-log')
        ->assertMissing('@world-admin');
});
