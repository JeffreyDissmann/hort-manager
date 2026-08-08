<?php

declare(strict_types=1);

use App\Models\User;

// The Hilfe page: an overview of chapters, each on its own URL. Guests can read
// it too — a parent may well look before their first sign-in.

it('opens a chapter from the overview and finds the way back', function () {
    $page = actAndVisit(User::factory()->parent()->create(), '/help');
    $page->assertSee('Ferien & Schließzeiten');

    $page->click('@help-topic-holidays');
    // Assert the content first: it waits for the chapter, so the path check that
    // follows isn't racing the client-side navigation.
    $page->assertSee('Ferienbetreuung: Tag für Tag anmelden')
        ->assertPathIs('/help/holidays')
        // The chapter footer links on to the others.
        ->assertSee('Alle Themen');

    $page->click('@help-back');
    $page->assertSee('In 4 Schritten startklar')->assertPathIs('/help');
});

it('shows the three kinds of „nicht da" in one chapter', function () {
    actAndVisit(User::factory()->parent()->create(), '/help/absences')
        ->assertSee('Krank oder „kommt nicht“')
        ->assertSee('Hortfrei')
        ->assertSee('Schließzeit');
});

it('is readable without signing in', function () {
    $page = visit('/help');
    $page->assertSee('Willkommen beim Hort-Manager')->assertSee('Zur Anmeldung');

    $page->click('@help-topic-getting-started');
    $page->assertSee('Wie melde ich mich an?')->assertPathIs('/help/getting-started');
});
