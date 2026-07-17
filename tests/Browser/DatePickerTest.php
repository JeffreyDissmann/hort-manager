<?php

declare(strict_types=1);

use App\Models\User;

// The shared DatePicker component in a form context (child date of birth).
it('picks a date with the shared date picker in a form', function () {
    $staff = User::factory()->staff()->create();
    $firstOfMonth = now()->startOfMonth()->toDateString(); // visible in the current month

    actAndVisit($staff, '/children/create')
        ->click('#date_of_birth')          // the input-style trigger
        ->assertPresent('@date-picker')    // shared calendar popover opens
        ->click("@date-pick-{$firstOfMonth}")
        ->assertMissing('@date-picker');   // choosing a day closes it
});

it('can clear an optional date', function () {
    $staff = User::factory()->staff()->create();
    $firstOfMonth = now()->startOfMonth()->toDateString();

    actAndVisit($staff, '/children/create')
        ->click('#date_of_birth')
        ->click("@date-pick-{$firstOfMonth}")  // set a value
        ->click('#date_of_birth')                // reopen
        ->assertPresent('@date-clear')           // clear shown (clearable + value)
        ->click('@date-clear')
        ->assertMissing('@date-picker');         // cleared and closed
});
