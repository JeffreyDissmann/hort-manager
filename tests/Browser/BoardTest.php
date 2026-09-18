<?php

declare(strict_types=1);

use App\Enums\DepartureStatus;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
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

it('writes the slot header as „15:00 Uhr", not „15:00UHR"', function () {
    // Vue condenses whitespace at the start of a span, so the plain space between the
    // time and „Uhr" disappeared — only visible in the rendered DOM.
    $staff = User::factory()->staff()->create();
    scheduledChild('Emma');

    $page = actAndVisit($staff, '/board');
    $header = $page->script(
        "[...document.querySelectorAll('p')].map((p) => p.textContent.trim()).find((t) => /^15:00/.test(t))"
    );

    // The space is a non-breaking one — „15:00" and „Uhr" belong together.
    expect(str_replace("\u{00a0}", ' ', (string) $header))->toBe('15:00 Uhr');
});

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

it('places a timed Aktivität as a card in the day\'s order', function () {
    $staff = User::factory()->staff()->create();
    scheduledChild('Frida'); // leaves at 15:00 (see the helper)
    DailyProgram::factory()->create([
        'date' => boardDate()->toDateString(), 'activity' => 'Waldbesuch',
        'activity_start' => '13:00', 'activity_end' => '14:30',
    ]);

    actAndVisit($staff, '/board')
        ->assertVisible('@board-activity-card')
        ->assertSeeIn('@board-activity-card', '13:00–14:30')
        ->assertNoJavaScriptErrors();
});

it('warns on the card when a pickup falls inside the Aktivität', function () {
    $staff = User::factory()->staff()->create();
    $child = scheduledChild('Frida'); // leaves at 15:00
    DailyProgram::factory()->create([
        'date' => boardDate()->toDateString(), 'activity' => 'Fußballtraining',
        'activity_start' => '14:30', 'activity_end' => '16:00',
    ]);

    actAndVisit($staff, '/board')
        ->assertVisible("@activity-conflict-{$child->id}")
        ->assertSee('Abholung liegt in der Aktivität „Fußballtraining"');
});

it('draws the Aktivität as a bar when a pickup falls inside it', function () {
    $staff = User::factory()->staff()->create();
    scheduledChild('Frida'); // leaves at 15:00 — inside the window below
    DailyProgram::factory()->create([
        'date' => boardDate()->toDateString(), 'activity' => 'Waldbesuch',
        'activity_start' => '14:30', 'activity_end' => '16:00',
    ]);

    actAndVisit($staff, '/board')
        ->assertVisible('@board-activity-bar')   // beside the pickups it covers
        ->assertMissing('@board-activity-card')
        ->assertNoJavaScriptErrors();
});

it('gives Hausaufgaben and Aktivität a lane each when their bars overlap', function () {
    $staff = User::factory()->staff()->create();
    scheduledChild('Frida'); // 15:00 — inside both windows
    HomeworkDefault::create(['weekday' => boardWeekday(), 'start_time' => '14:00', 'end_time' => '16:00']);
    DailyProgram::factory()->create([
        'date' => boardDate()->toDateString(), 'activity' => 'Waldbesuch',
        'activity_start' => '14:30', 'activity_end' => '16:00',
    ]);

    actAndVisit($staff, '/board')
        ->assertVisible('@board-homework-bar')
        ->assertVisible('@board-activity-bar')
        ->assertNoJavaScriptErrors();
});
