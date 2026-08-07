<?php

declare(strict_types=1);

use App\Enums\AbsenceReason;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;

// „Statistik" in a real browser — the charts are canvas, so this is the only layer
// that can tell whether anything was actually drawn.

it('draws the pickup-time chart for an admin', function () {
    $admin = User::factory()->staff()->admin()->create();
    DailyDeparture::create([
        'child_id' => Child::factory()->create()->id,
        'date' => today()->subWeek()->toDateString(),
        'planned_time' => '15:00',
    ]);

    $page = actAndVisit($admin, '/admin/statistics')
        ->assertSee('Wann die Kinder gehen')
        ->assertPresent('@pickup-times');

    // Chart.js paints to a canvas: an unmounted or empty chart leaves no canvas at all.
    expect($page->script("document.querySelector('[data-testid=pickup-times] canvas')?.width ?? 0"))
        ->toBeGreaterThan(0);
});

it('gives „krank" and „kommt nicht" a bar each', function () {
    $admin = User::factory()->staff()->admin()->create();
    Absence::create([
        'child_id' => Child::factory()->create()->id,
        'date' => today()->subWeek()->toDateString(),
        'reason' => AbsenceReason::Sick,
        'comment' => 'Fieber',
    ]);

    // The legend is what names the two bars — with one series it isn't drawn at all.
    actAndVisit($admin, '/admin/statistics')
        ->assertSee('Krank und abwesend')
        ->assertPresent('@absences');
});

it('switches the period from the buttons', function () {
    $admin = User::factory()->staff()->admin()->create();

    actAndVisit($admin, '/admin/statistics')
        ->click('@range-year')
        ->assertQueryStringHas('range', 'year')
        // The chosen period survives the plan/reality toggle, and the other way round.
        ->click('@basis-actual')
        ->assertQueryStringHas('range', 'year')
        ->assertQueryStringHas('basis', 'actual')
        ->assertSee('Tatsächliche Zeiten')
        ->click('@range-quarter')
        ->assertQueryStringHas('basis', 'actual');
});
