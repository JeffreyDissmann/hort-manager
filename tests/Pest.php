<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

// Browser tests (Pest 4 + Playwright). Unlike Dusk, the browser shares the app
// process, so RefreshDatabase works and no throwaway db / served instance is needed.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Act as the given user, visit a page, and close the „Was ist neu?" popup that
 * auto-opens on a fresh browser (its <dialog> backdrop would block clicks).
 */
function actAndVisit(User $user, string $url)
{
    test()->actingAs($user);

    $page = visit($url);
    $page->script("document.querySelectorAll('dialog[open]').forEach((d) => d.close())");

    return $page;
}

/** The date the Heute board targets (today, or the next weekday on weekends). */
function boardDate(): Carbon
{
    $date = Carbon::today();

    while ($date->isWeekend()) {
        $date->addDay();
    }

    return $date;
}

/** The weekday the Heute board targets (today, or the next weekday on weekends). */
function boardWeekday(): int
{
    return boardDate()->isoWeekday();
}
