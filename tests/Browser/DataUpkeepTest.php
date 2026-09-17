<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;

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

it('lets an admin change how long records are kept', function () {
    $admin = User::factory()->staff()->admin()->create();

    // The cutoff line is rendered from the *server's* value, so waiting for the new date
    // is what proves the save landed — „Gelöscht wird alles vor dem" is on the page
    // before the request, too, and asserting only that raced the POST on slower runners.
    $cutoff = Carbon::today()->subMonths(12)->locale('de')->isoFormat('D. MMMM YYYY');

    actAndVisit($admin, '/admin/data-upkeep')
        ->assertSee('Aufbewahrung')
        ->select('@retention-months', '12')
        ->assertSee($cutoff);

    expect(Setting::retentionMonths())->toBe(12);
});
