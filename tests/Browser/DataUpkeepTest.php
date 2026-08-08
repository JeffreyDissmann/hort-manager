<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\User;

// „Datenpflege" — the page an admin opens to find out whether anything needs doing.

it('sends an admin from an open check to where it gets fixed', function () {
    $admin = User::factory()->staff()->admin()->create();
    Child::factory()->create(['name' => 'Ohne Plan']);

    actAndVisit($admin, '/admin/data-upkeep')
        ->assertSee('Kinder ohne Stammplan')
        ->click('@check-children_without_plan')
        ->assertPathIs('/children');
});

it('says so plainly when there is nothing to do', function () {
    $admin = User::factory()->staff()->admin()->create();

    actAndVisit($admin, '/admin/data-upkeep')
        ->assertSee('Alles gepflegt')
        // No rows at all, so no counts to misread as work.
        ->assertMissing('@check-children_without_plan');
});
