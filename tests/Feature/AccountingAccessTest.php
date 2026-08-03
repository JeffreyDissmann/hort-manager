<?php

declare(strict_types=1);

use App\Enums\AccountingAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('shares the write flag that drives hiding write controls in the UI', function () {
    // Reader: can_read true, can_write false → the frontend hides write CTAs.
    $this->actingAs(User::factory()->accountingReader()->create())
        ->get('/accounting/reports')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.can_read_accounting', true)
            ->where('auth.user.can_write_accounting', false));

    // Writer: both true.
    $this->actingAs(User::factory()->accountingWriter()->create())
        ->get('/accounting/reports')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.can_read_accounting', true)
            ->where('auth.user.can_write_accounting', true));
});

it('lets a read-only user view accounting but not write', function () {
    $reader = User::factory()->accountingReader()->create();
    $this->actingAs($reader);

    // Read pages are allowed…
    $this->get('/accounting/reports')->assertOk();
    $this->get('/accounting/bookings')->assertOk();

    // …but any write route is forbidden.
    $this->get('/accounting/bookings/create')->assertForbidden();
    $this->post('/accounting/transfers', [])->assertForbidden();
});

it('lets a write user reach both read and write routes', function () {
    $writer = User::factory()->accountingWriter()->create();
    $this->actingAs($writer);

    $this->get('/accounting/reports')->assertOk();
    $this->get('/accounting/bookings/create')->assertOk();
});

it('forbids a user with no accounting access from everything', function () {
    $none = User::factory()->create(); // default: none
    $this->actingAs($none);

    $this->get('/accounting')->assertForbidden();
    $this->get('/accounting/reports')->assertForbidden();
    $this->get('/accounting/bookings/create')->assertForbidden();
});

it('is independent of admin — an admin without access is still forbidden', function () {
    // Admin (user management) does not imply accounting access.
    $admin = User::factory()->admin()->create();
    expect($admin->accounting_access)->toBe(AccountingAccess::None);

    $this->actingAs($admin)->get('/accounting/reports')->assertForbidden();
});

it('lets an admin grant accounting access to any user', function () {
    $admin = User::factory()->admin()->create();
    $treasurer = User::factory()->create(); // a plain parent, not an admin

    $this->actingAs($admin)
        ->patch(route('users.update', $treasurer), [
            'role' => $treasurer->role->value,
            'is_admin' => false,
            'accounting_access' => 'write',
        ]);

    expect($treasurer->refresh()->accounting_access)->toBe(AccountingAccess::Write)
        ->and($treasurer->is_admin)->toBeFalse(); // granted without making them an admin
});
