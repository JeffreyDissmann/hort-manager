<?php

declare(strict_types=1);

use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HolidayCareDay;
use App\Models\HomeworkDefault;
use App\Support\DayWindows;

// The one containment rule the whole app asks about a pickup, and the window shapes it
// is asked with. Open at both ends: the start and the end of a window are still free.

it('only counts a pickup strictly inside the window', function () {
    expect(DayWindows::contains('14:01', '14:00', '15:00'))->toBeTrue()
        ->and(DayWindows::contains('14:59', '14:00', '15:00'))->toBeTrue()
        // Leaving exactly as an Aktivität starts is fine: the child goes before it begins.
        ->and(DayWindows::contains('14:00', '14:00', '15:00'))->toBeFalse()
        // Leaving exactly when the Hausaufgaben end is fine — that's the normal case.
        ->and(DayWindows::contains('15:00', '14:00', '15:00'))->toBeFalse()
        ->and(DayWindows::contains('13:59', '14:00', '15:00'))->toBeFalse();
});

it('says no when anything is missing', function () {
    expect(DayWindows::contains(null, '14:00', '15:00'))->toBeFalse()
        ->and(DayWindows::contains('14:30', null, '15:00'))->toBeFalse()
        ->and(DayWindows::contains('14:30', '14:00', null))->toBeFalse();
});

it('compares seconds-bearing times from the database', function () {
    // Times come off SQLite as „14:00:00"; the rule works on H:i.
    expect(DayWindows::contains('14:30', '14:00:00', '15:00:00'))->toBeTrue();
});

it('builds the homework window from the weekday default and marks it as such', function () {
    $default = new HomeworkDefault(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

    $windows = DayWindows::program(null, $default);

    expect($windows)->toHaveCount(1)
        ->and($windows[0])->toMatchArray([
            'kind' => 'homework', 'from' => '14:00', 'to' => '15:00', 'from_default' => true,
        ]);
});

it('marks a homework window that the day overrides', function () {
    $program = new DailyProgram(['homework_start' => '15:00', 'homework_end' => '16:00']);
    $default = new HomeworkDefault(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);

    expect(DayWindows::program($program, $default)[0])->toMatchArray([
        'from' => '15:00', 'to' => '16:00', 'from_default' => false,
    ]);
});

it('drops homework on a Ferienbetreuung day', function () {
    $default = new HomeworkDefault(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00']);
    $program = new DailyProgram([
        'activity' => 'Waldtag', 'activity_start' => '09:00', 'activity_end' => '12:00',
    ]);

    // In den Ferien there are no Hausaufgaben — the Aktivität stays.
    $windows = DayWindows::program($program, $default, isCareDay: true);

    expect($windows)->toHaveCount(1)
        ->and($windows[0])->toMatchArray(['kind' => 'activity', 'name' => 'Waldtag']);
});

it('ignores a timed window without an Aktivität', function () {
    $program = new DailyProgram(['activity' => null, 'activity_start' => '09:00', 'activity_end' => '12:00']);

    expect(DayWindows::program($program, null))->toBe([]);
});

it('cannot judge a trip without a return time', function () {
    expect(DayWindows::excursion(new Excursion(['name' => 'Zoo', 'depart_at' => '13:30'])))->toBeNull()
        ->and(DayWindows::excursion(null))->toBeNull();
});

it('treats a trip without a departure time as away from the morning on', function () {
    expect(DayWindows::excursion(new Excursion(['name' => 'Zoo', 'return_at' => '17:00'])))
        ->toMatchArray(['kind' => 'excursion', 'name' => 'Zoo', 'from' => '00:00', 'to' => '17:00']);
});

it('builds the Betreuungszeit window of a care day', function () {
    // The period is only read for its name; set it explicitly so this stays a unit test.
    $day = (new HolidayCareDay(['starts_at' => '08:30', 'ends_at' => '16:00']))
        ->setRelation('period', null);

    expect(DayWindows::care($day))->toMatchArray(['kind' => 'care', 'from' => '08:30', 'to' => '16:00'])
        ->and(DayWindows::care(null))->toBeNull();
});

it('returns every window a time falls into', function () {
    $windows = [
        ['kind' => 'homework', 'name' => null, 'from' => '14:00', 'to' => '15:00', 'from_default' => true],
        ['kind' => 'activity', 'name' => 'Chor', 'from' => '14:30', 'to' => '16:00', 'from_default' => false],
        ['kind' => 'excursion', 'name' => 'Zoo', 'from' => '08:00', 'to' => '12:00', 'from_default' => false],
    ];

    expect(array_column(DayWindows::hits('14:45', $windows), 'kind'))->toBe(['homework', 'activity'])
        ->and(DayWindows::hits('17:00', $windows))->toBe([])
        // A pickup right at a start — a handover as the group leaves, too — is fine.
        ->and(DayWindows::hits('08:00', $windows))->toBe([])
        ->and(DayWindows::hits('14:00', $windows))->toBe([]);
});

it('counts an arrival right at the departure as missing the group, but not one at the return', function () {
    expect(DayWindows::arrivesDuring('13:30', '13:30', '17:00'))->toBeTrue()
        ->and(DayWindows::arrivesDuring('16:59', '13:30', '17:00'))->toBeTrue()
        ->and(DayWindows::arrivesDuring('17:00', '13:30', '17:00'))->toBeFalse()
        ->and(DayWindows::arrivesDuring('13:29', '13:30', '17:00'))->toBeFalse()
        ->and(DayWindows::arrivesDuring(null, '13:30', '17:00'))->toBeFalse();
});
