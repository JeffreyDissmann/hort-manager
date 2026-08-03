<?php

declare(strict_types=1);

use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;

/**
 * The Heute board through a real browser — the layer that catches DOM regressions
 * (feature/Inertia tests only see props). The browser shares the app process, so the
 * clock is real "today"; we schedule children for the board's target weekday.
 */
function scheduledChild(string $name): Child
{
    return Child::factory()->scheduledOn(boardWeekday(), '15:00')->create(['name' => $name]);
}

it('lets staff mark a child picked up', function () {
    $staff = User::factory()->staff()->create();
    $child = scheduledChild('Emma');

    actAndVisit($staff, '/board')
        ->assertSee('Emma')
        ->assertPresent("@mark-picked-up-{$child->id}")
        ->click("@mark-picked-up-{$child->id}")
        ->assertPresent("@undo-{$child->id}")
        ->assertMissing("@mark-picked-up-{$child->id}");

    expect(DailyDeparture::where('child_id', $child->id)->whereDate('date', today())->value('status'))
        ->toBe(DepartureStatus::PickedUp);
});

it('lets staff send a child home and undo it', function () {
    $staff = User::factory()->staff()->create();
    $child = scheduledChild('Ben');

    actAndVisit($staff, '/board')
        ->click("@mark-sent-home-{$child->id}")
        ->assertPresent("@undo-{$child->id}")
        ->click("@undo-{$child->id}")
        ->assertPresent("@mark-picked-up-{$child->id}");

    expect(DailyDeparture::where('child_id', $child->id)->whereDate('date', today())->value('status'))
        ->toBe(DepartureStatus::Present);
});

it('hides the mark buttons from parents', function () {
    $parent = User::factory()->parent()->create();
    $child = Child::factory()->scheduledOn(boardWeekday(), '15:00')->withGuardian($parent)->create(['name' => 'Mia']);

    actAndVisit($parent, '/board')
        ->assertSee('Mia')
        ->assertMissing("@mark-picked-up-{$child->id}")
        ->assertMissing("@mark-sent-home-{$child->id}");
});

it('lets staff report a child sick from the board', function () {
    $staff = User::factory()->staff()->create();
    $child = scheduledChild('Nora');

    actAndVisit($staff, '/board')
        ->click("@report-sick-{$child->id}")
        ->fill("@absence-comment-{$child->id}", 'Fieber')
        ->click("@absence-submit-{$child->id}")
        ->assertMissing("@absence-submit-{$child->id}"); // wait for the POST to land

    expect(Absence::where('child_id', $child->id)->whereDate('date', today())->first())
        ->reason->value->toBe('sick')
        ->comment->toBe('Fieber');
});

it('lets staff report a child as away („Kommt nicht") from the board', function () {
    $staff = User::factory()->staff()->create();
    $child = scheduledChild('Paul');

    actAndVisit($staff, '/board')
        ->click("@report-away-{$child->id}")
        ->fill("@absence-comment-{$child->id}", 'Termin')
        ->click("@absence-submit-{$child->id}")
        ->assertMissing("@absence-submit-{$child->id}"); // wait for the POST to land

    expect(Absence::where('child_id', $child->id)->whereDate('date', today())->first())
        ->reason->value->toBe('away')
        ->comment->toBe('Termin');
});

it('shows a confirmed excursion participant on the board', function () {
    $staff = User::factory()->staff()->create();
    $child = scheduledChild('Frida');

    $excursion = Excursion::factory()->create([
        'name' => 'Waldtag',
        'date' => boardDate()->toDateString(),
        'rsvp_deadline' => boardDate()->toDateString(),
    ]);
    $excursion->children()->attach($child->id, ['response' => true]);

    actAndVisit($staff, '/board')
        ->assertSee('Frida')
        ->assertSee('Waldtag'); // the excursion overlay badge
});
