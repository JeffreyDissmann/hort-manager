<?php

declare(strict_types=1);

use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;

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

it('greets a shut Hort with the holiday screen', function () {
    // A guest sees it too — the landing page is where a family lands from the app icon.
    $today = Carbon::today();
    HolidayPeriod::factory()->onDay($today->toDateString())->create(['name' => 'Fortbildung']);

    $next = $today->copy()->addDay();
    while ($next->isWeekend()) {
        $next->addDay();
    }

    visit('/')
        ->assertPresent('@welcome-closure')
        ->assertSee('Fortbildung')
        ->assertSee('Nur noch heute geschlossen')
        ->assertSee($next->translatedFormat('l, j. F'))
        ->assertSee('ganz normaler Hort-Tag');
});
