<?php

declare(strict_types=1);

use App\Models\DailyProgram;
use App\Models\HomeworkDefault;
use App\Models\User;

// „Programm": an Aktivität is untimed by default; „Mit Uhrzeit" gives it a window.
it('gives an activity a time window only when asked', function () {
    $staff = User::factory()->staff()->create();
    $date = boardDate()->toDateString();

    $page = actAndVisit($staff, '/program');

    $page->type("#activity-{$date}", 'Waldtag')
        ->assertMissing("@activity-time-{$date}-start")   // untimed by default
        ->click("@activity-timed-{$date}")
        ->select("@activity-time-{$date}-start-hour", '09')
        ->select("@activity-time-{$date}-start-minute", '00')
        ->select("@activity-time-{$date}-end-hour", '12')
        ->select("@activity-time-{$date}-end-minute", '00')
        ->click('@save-program')
        ->assertNoJavaScriptErrors();

    $program = DailyProgram::firstWhere('date', $date);
    expect($program->activityText())->toBe('Waldtag (09:00–12:00)');
});

it('starts a timed activity right after the homework slot', function () {
    $staff = User::factory()->staff()->create();
    $date = boardDate()->toDateString();
    HomeworkDefault::create(['weekday' => boardWeekday(), 'start_time' => '14:00', 'end_time' => '15:00']);

    actAndVisit($staff, '/program')
        ->type("#activity-{$date}", 'Basteln')
        ->click("@activity-timed-{$date}")
        // Homework runs until 15:00, so the activity starts there (one hour by default).
        ->assertValue("@activity-time-{$date}-start-hour", '15')
        ->assertValue("@activity-time-{$date}-end-hour", '16')
        ->click('@save-program');

    expect(DailyProgram::firstWhere('date', $date)->activityText())->toBe('Basteln (15:00–16:00)');
});
