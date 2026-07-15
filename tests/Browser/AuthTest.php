<?php

declare(strict_types=1);

use App\Models\User;

// The e-mail/password sign-in form (Slack SSO is the primary path, but this exists).
it('signs a user in with email and password', function () {
    User::factory()->staff()->create(['email' => 'frau.mueller@hort.test']); // password: „password"

    visit('/login')
        ->fill('email', 'frau.mueller@hort.test')
        ->fill('password', 'password')
        ->click('@login')
        ->assertPathIs('/dashboard');

    $this->assertAuthenticated();
});

it('rejects bad credentials', function () {
    User::factory()->create(['email' => 'frau.mueller@hort.test']);

    visit('/login')
        ->fill('email', 'frau.mueller@hort.test')
        ->fill('password', 'wrong-password')
        ->click('@login')
        ->assertSee(__('auth.failed'));

    $this->assertGuest();
});
