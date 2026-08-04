<?php

declare(strict_types=1);

use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareAnswer;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;

// Ferienbetreuung end to end: staff offer the days, parents tick them, and the
// sign-up *is* the child's plan for that day.

/** A Ferienbetreuung next month, with its weekdays already offered. */
function carePeriod(?string $deadline = null): HolidayPeriod
{
    $monday = Carbon::today()->addMonth()->startOfWeek(Carbon::MONDAY);

    $period = HolidayPeriod::factory()->care()->create([
        'name' => 'Sommer-Ferienbetreuung',
        'starts_on' => $monday->toDateString(),
        'ends_on' => $monday->copy()->addDays(4)->toDateString(),
        'registration_deadline' => $deadline ?? Carbon::today()->addWeek()->toDateString(),
    ]);
    $period->generateCareDays();

    return $period;
}

it('lets staff set up a Ferienbetreuung with its days', function () {
    $staff = User::factory()->staff()->create();

    // A Wednesday in the visible month, so one calendar click gives exactly one
    // offered weekday — a weekend date would assert nothing.
    $day = now()->startOfMonth()->addDays(20);
    while (! $day->isWednesday()) {
        $day->addDay();
    }

    actAndVisit($staff, '/closures')
        ->click('@type-care')                 // Geschlossen → Ferienbetreuung
        ->type('@closure-name', 'Herbst-Ferienbetreuung')
        ->click('#closure-from')
        ->click('@date-pick-'.$day->toDateString())
        ->click('@closure-save')
        ->assertSee('Herbst-Ferienbetreuung');

    $period = HolidayPeriod::where('name', 'Herbst-Ferienbetreuung')->first();

    expect($period->isCare())->toBeTrue()
        ->and($period->careDays()->count())->toBe(1)
        ->and(HolidayCareDay::short($period->careDays()->first()->starts_at))->toBe('08:30');
});

it('lets staff change a day’s Betreuungszeit', function () {
    $staff = User::factory()->staff()->create();
    $period = carePeriod();
    $first = $period->careDays()->orderBy('date')->first();

    // A period is edited on its own page, the way an Ausflug is.
    actAndVisit($staff, "/closures/{$period->id}/edit")
        ->assertSee('08:30–16:00')
        ->click("@care-day-edit-{$first->id}")
        ->select("@care-start-{$first->id}-hour", '09')
        ->select("@care-start-{$first->id}-minute", '00')
        ->click("@care-day-save-{$first->id}")
        ->assertSee('09:00–16:00');

    expect(HolidayCareDay::short($first->fresh()->starts_at))->toBe('09:00');
});

it('lets a parent tick the days their child will come', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->create(['name' => 'Mia']);
    $parent->children()->attach($child);

    $period = carePeriod();
    [$monday, $tuesday] = $period->careDays()->orderBy('date')->take(2)->get()->all();

    // Parents sign up on „Ausflüge & Ferien" — /care is the staff screen.
    actAndVisit($parent, '/polls')
        ->assertSee('Sommer-Ferienbetreuung')
        ->assertSee('noch nicht beantwortet')
        ->click("@care-pick-{$child->id}-{$monday->id}")
        ->click("@care-save-{$period->id}-{$child->id}")
        ->assertDontSee('noch nicht beantwortet')
        // The saved day stays ticked — the boxes re-seed from the server, so an
        // empty box here would tell the family they aren't registered.
        ->assertChecked("@care-pick-{$child->id}-{$monday->id}")
        ->assertNotChecked("@care-pick-{$child->id}-{$tuesday->id}");

    // The tick *is* the plan: a DailyDeparture on that date, none on the others.
    expect(DailyDeparture::where('child_id', $child->id)->pluck('date')->map->toDateString()->all())
        ->toBe([$monday->date->toDateString()])
        ->and(HolidayCareAnswer::where('child_id', $child->id)->exists())->toBeTrue()
        ->and(DailyDeparture::whereDate('date', $tuesday->date)->exists())->toBeFalse();
});

it('opens a Ferienbetreuung from the list to set it up', function () {
    $staff = User::factory()->staff()->create();
    Child::factory()->create(['name' => 'Mia']);
    $period = carePeriod();

    // The list is a list; a period is set up on its own page, like an Ausflug.
    $page = actAndVisit($staff, '/closures');
    $page->script("document.querySelectorAll('dialog[open]').forEach((d) => d.close())");
    $page->click("@care-edit-{$period->id}");

    $page->assertSee('Angebotene Tage')
        ->assertSee('Wer ist angemeldet?')
        ->assertSee('Mia')
        ->assertPathIs("/closures/{$period->id}/edit");
});

it('sends an unregistered care day to the sign-up instead of an editor', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->withGuardian($parent)->create(['name' => 'Mia']);

    // A Ferienbetreuung covering today, which Mia is not signed up for.
    $today = Carbon::today();
    $period = HolidayPeriod::factory()->care()->create([
        'name' => 'Sommer-Ferienbetreuung',
        'starts_on' => $today->toDateString(),
        'ends_on' => $today->copy()->addDays(2)->toDateString(),
        'registration_deadline' => $today->copy()->addDay()->toDateString(),
    ]);
    $period->generateCareDays();

    $page = actAndVisit($parent, '/weekly-plan');
    $page->script("document.querySelectorAll('dialog[open]').forEach((d) => d.close())");

    $page->assertSee('Nicht angemeldet')
        // Tapping the cell must not open the day editor — there is nothing to plan.
        ->click("@wp-cell-{$child->id}-{$today->toDateString()}")
        ->assertMissing('@save');

    // The way out is one link in the week's banner, not one per row or per cell.
    $page->click('@wp-care-signup')->assertPathIs('/polls');
});

it('puts trips and Ferienbetreuung on one page for parents', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->create(['name' => 'Mia']);
    $parent->children()->attach($child);
    carePeriod();

    // The sign-up sheet sits above the trips, under one heading …
    $page = actAndVisit($parent, '/polls');
    $page->script("document.querySelectorAll('dialog[open]').forEach((d) => d.close())");
    $page->assertSee('Ausflüge & Ferien')
        ->assertPresent('@poll-care')
        ->assertSee('Sommer-Ferienbetreuung');

    // … and /care sends a parent here rather than showing a second sheet.
    $page = visit('/care');
    $page->assertPathIs('/polls');
});

it('nudges a parent who has not answered, and stops once they have', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->create(['name' => 'Mia']);
    $parent->children()->attach($child);
    $period = carePeriod();

    // The banner rides on every page, not just /care.
    actAndVisit($parent, '/board')
        ->assertPresent('@care-reminder')
        ->assertSee('Sommer-Ferienbetreuung');

    HolidayCareAnswer::create([
        'holiday_period_id' => $period->id,
        'child_id' => $child->id,
        'answered_by' => $parent->id,
        'answered_at' => now(),
    ]);

    // „Keine Tage" is an answer too — the nudge has to stop either way.
    actAndVisit($parent, '/board')->assertMissing('@care-reminder');
});

it('locks the sign-up once the Anmeldeschluss has passed', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->create(['name' => 'Mia']);
    $parent->children()->attach($child);

    $period = carePeriod(deadline: Carbon::yesterday()->toDateString());
    $firstDay = $period->careDays()->orderBy('date')->first();

    actAndVisit($parent, '/polls')
        ->assertSee('Anmeldeschluss war am')
        // No save button and no tickable days once the window has closed.
        ->assertMissing("@care-save-{$period->id}-{$child->id}")
        ->assertPresent("@care-pick-{$child->id}-{$firstDay->id}")
        ->assertDisabled("@care-pick-{$child->id}-{$firstDay->id}");
});

it('lets staff sign a child up even after the deadline', function () {
    $staff = User::factory()->staff()->create();
    $child = Child::factory()->create(['name' => 'Mia']);

    $period = carePeriod(deadline: Carbon::yesterday()->toDateString());
    $monday = $period->careDays()->orderBy('date')->first();

    // Staff fill the roster on the period's page — deadline or not.
    actAndVisit($staff, "/closures/{$period->id}/edit")
        ->assertSee('Erzieher:innen können weiterhin eintragen')
        ->click("@care-pick-{$child->id}-{$monday->id}")
        ->click("@care-save-{$period->id}-{$child->id}")
        // Wait for the save to land before reading the database.
        ->assertDontSee('noch nicht beantwortet');

    expect(DailyDeparture::whereDate('date', $monday->date)->where('child_id', $child->id)->exists())
        ->toBeTrue();
});
