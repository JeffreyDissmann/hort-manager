<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

// The admin „Meine Rolle" toggle in the menu (a real, persisted role switch).
it('lets an admin switch from staff to parent from the menu', function () {
    $admin = User::factory()->staff()->admin()->create();
    $child = scheduledChild('Zoe');

    actAndVisit($admin, '/tagesboard')
        ->assertPresent("@mark-picked-up-{$child->id}") // staff: buttons present
        ->click('@user-menu')
        ->click('@role-parent')
        // Now a parent with no children of their own → the board shows none of them.
        ->assertMissing("@mark-picked-up-{$child->id}");

    expect($admin->refresh()->role)->toBe(UserRole::Parent);
});

it('lets an admin switch from parent to staff from the menu', function () {
    $admin = User::factory()->parent()->admin()->create();
    $child = scheduledChild('Kai');

    actAndVisit($admin, '/tagesboard')
        ->assertSee('Kai')
        ->assertMissing("@mark-picked-up-{$child->id}") // parent: no mark buttons
        ->click('@user-menu')
        ->click('@role-staff')
        ->assertPresent("@mark-picked-up-{$child->id}"); // staff: buttons appear

    expect($admin->refresh()->role)->toBe(UserRole::Staff);
});

it('hides the role toggle from non-admins', function () {
    $staff = User::factory()->staff()->create();

    actAndVisit($staff, '/tagesboard')
        ->click('@user-menu')
        ->assertMissing('@role-staff')
        ->assertMissing('@role-parent');
});
