<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('forbids non-admins from the accounts page', function () {
    $staff = User::factory()->staff()->create(); // staff, but not admin

    $this->actingAs($staff)->get('/accounting/accounts')->assertForbidden();
});

it('lists accounts with their balance for admins', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->withOpeningBalance(10000)->create(['name' => 'Konto']);
    Booking::factory()->for($account)->create(['amount_cents' => 5000]);
    Booking::factory()->for($account)->draft()->create(['amount_cents' => 9999]); // ignored

    $this->actingAs($admin)
        ->get('/accounting/accounts')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Accounts/Index')
            ->where('accounts.0.name', 'Konto')
            ->where('accounts.0.balance_cents', 15000));
});

it('creates an account, converting euros to cents', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/accounting/accounts', [
            'name' => 'Bar-Kasse',
            'iban' => 'de89 3704 0044 0532 0130 00',
            'opening_balance' => '123.45',
            'opening_balance_date' => '2026-01-01',
            'active' => true,
        ])
        ->assertRedirect('/accounting/accounts');

    $account = Account::firstWhere('name', 'Bar-Kasse');
    expect($account->opening_balance_cents)->toBe(12345)
        ->and($account->iban)->toBe('DE89370400440532013000') // normalised
        ->and($account->active)->toBeTrue();
});

it('updates an account', function () {
    $admin = User::factory()->admin()->create();
    $account = Account::factory()->create(['name' => 'Alt']);

    $this->actingAs($admin)
        ->put("/accounting/accounts/{$account->id}", [
            'name' => 'Neu',
            'opening_balance' => '0',
            'active' => false,
        ])
        ->assertRedirect('/accounting/accounts');

    expect($account->refresh()->name)->toBe('Neu')
        ->and($account->active)->toBeFalse();
});

it('deletes an empty account but refuses one with bookings', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $empty = Account::factory()->create();
    $this->delete("/accounting/accounts/{$empty->id}")->assertRedirect('/accounting/accounts');
    expect(Account::find($empty->id))->toBeNull();

    $used = Account::factory()->create();
    Booking::factory()->for($used)->create();
    $this->delete("/accounting/accounts/{$used->id}");
    expect(Account::find($used->id))->not->toBeNull();
});

it('validates the account name', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/accounting/accounts', ['name' => '', 'active' => true])
        ->assertSessionHasErrors('name');
});

it('rejects a non-IBAN with a friendly message', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/accounting/accounts', ['name' => 'Konto', 'iban' => '6470297113', 'active' => true])
        ->assertSessionHasErrors(['iban' => __('accounting.accounts.iban_invalid')]);
});
