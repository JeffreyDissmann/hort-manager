<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
use App\Models\User;

// The standing „Abholzeiten prüfen" summary above every page, for parents only.
it('lists a parent\'s colliding pickups above the page', function () {
    $parent = User::factory()->parent()->create();
    $date = boardDate()->toDateString();
    $weekday = boardWeekday();

    // Nina's Stammplan collides with the weekday's homework default — every week.
    Child::factory()->scheduledOn($weekday, '14:30')->withGuardian($parent)->create(['name' => 'Nina']);
    HomeworkDefault::create(['weekday' => $weekday, 'start_time' => '14:00', 'end_time' => '15:00']);

    // Tom's pickup falls in today's Aktivität and in the trip he joins.
    $tom = Child::factory()->scheduledOn($weekday, '15:30')->withGuardian($parent)->create(['name' => 'Tom']);
    DailyProgram::factory()->create([
        'date' => $date, 'activity' => 'Fußballtraining',
        'activity_start' => '15:00', 'activity_end' => '16:00',
    ]);
    $trip = Excursion::factory()->create(['name' => 'Zoo', 'date' => $date, 'depart_at' => '13:30', 'return_at' => '17:00']);
    $trip->children()->syncWithoutDetaching([$tom->id => ['response' => true]]);

    actAndVisit($parent, '/board')
        ->assertVisible('@pickup-clash-banner')
        ->assertSeeIn('@pickup-clash-banner', 'Nina')
        ->assertSeeIn('@pickup-clash-banner', 'in der Hausaufgabenzeit (14:00–15:00).')
        ->assertSeeIn('@pickup-clash-banner', 'in der Aktivität „Fußballtraining" (15:00–16:00).')
        ->assertSeeIn('@pickup-clash-banner', 'im Ausflug „Zoo" (13:30–17:00).')
        ->assertNoJavaScriptErrors();
});

it('can be dismissed for the session, and comes back on a new one', function () {
    $parent = User::factory()->parent()->create();
    Child::factory()->scheduledOn(boardWeekday(), '14:30')->withGuardian($parent)->create(['name' => 'Nina']);
    HomeworkDefault::create(['weekday' => boardWeekday(), 'start_time' => '14:00', 'end_time' => '15:00']);

    $page = actAndVisit($parent, '/board');

    $page->assertVisible('@pickup-clash-banner')
        ->click('@pickup-clash-dismiss')
        ->assertMissing('@pickup-clash-banner')
        ->navigate('/weekly-plan')
        ->assertMissing('@pickup-clash-banner');   // stays away while browsing

    // Nothing was written server-side: a fresh session shows it again.
    $page->script('sessionStorage.clear()');
    $page->navigate('/board')->assertVisible('@pickup-clash-banner');
});

it('comes back when a new clash appears, even after being dismissed', function () {
    $parent = User::factory()->parent()->create();
    $date = boardDate()->toDateString();
    Child::factory()->scheduledOn(boardWeekday(), '14:30')->withGuardian($parent)->create(['name' => 'Nina']);
    $tom = Child::factory()->scheduledOn(boardWeekday(), '15:30')->withGuardian($parent)->create(['name' => 'Tom']);
    HomeworkDefault::create(['weekday' => boardWeekday(), 'start_time' => '14:00', 'end_time' => '15:00']);

    $page = actAndVisit($parent, '/board');
    $page->click('@pickup-clash-dismiss')->assertMissing('@pickup-clash-banner');

    // A trip Tom joins now collides with his pickup — that's news, so say it.
    $trip = Excursion::factory()->create(['name' => 'Zoo', 'date' => $date, 'depart_at' => '13:30', 'return_at' => '17:00']);
    $trip->children()->syncWithoutDetaching([$tom->id => ['response' => true]]);

    $page->navigate('/board')
        ->assertVisible('@pickup-clash-banner')
        ->assertSeeIn('@pickup-clash-banner', 'Zoo');
});

it('stays away from staff, who have the board itself', function () {
    $staff = User::factory()->staff()->create();
    Child::factory()->scheduledOn(boardWeekday(), '14:30')->create(['name' => 'Nina']);
    HomeworkDefault::create(['weekday' => boardWeekday(), 'start_time' => '14:00', 'end_time' => '15:00']);

    actAndVisit($staff, '/board')->assertMissing('@pickup-clash-banner');
});
