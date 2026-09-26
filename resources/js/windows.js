/**
 * The day's timed windows on the client side — Hausaufgaben, a timed Aktivität, an
 * Ausflug — and the one question asked of them: does a pickup fall inside?
 *
 * Mirrors `App\Support\DayWindows` on the server, down to the half-open interval
 * `[from, to)`: leaving exactly when the Hausaufgaben end is fine. Times are „HH:MM"
 * strings, which compare correctly as strings — `toMinutes` exists only for the layout
 * maths (row spans, band offsets), not for this test.
 */

/** Half-open containment: `from <= time < to`. False if anything is missing. */
export function inWindow(time, from, to) {
    if (!time || !from || !to) {
        return false;
    }

    return time >= from && time < to;
}

/**
 * The program windows of one day, in starting order: the effective Hausaufgabenzeit
 * and a timed Aktivität. `label` is what the board and the timetable print on the bar.
 *
 * The server already drops homework on a Ferienbetreuung day and on a Schließtag, so
 * a present `homework_start` here means the slot really applies.
 *
 * @param {object|null} program the day's program (`homework_start`, `activity`, …)
 * @param {(key: string) => string} translate the caller's `$t`, for the homework label
 * @returns {Array<{kind: string, start: string, end: string, label: string}>}
 */
export function programWindows(program, translate) {
    const windows = [];

    if (program?.homework_start && program?.homework_end) {
        windows.push({
            kind: 'homework',
            start: program.homework_start,
            end: program.homework_end,
            label: translate('board.homework'),
        });
    }

    if (program?.activity && program.activity_start && program.activity_end) {
        windows.push({
            kind: 'activity',
            start: program.activity_start,
            end: program.activity_end,
            label: program.activity,
        });
    }

    return windows.sort((a, b) => (a.start < b.start ? -1 : 1));
}

/**
 * Does the pickup fall inside the trip? A trip without a return time can't be judged —
 * nobody knows when the group is back; a missing departure counts as „from the morning".
 */
export function inExcursion(time, excursion) {
    if (!excursion?.return_at) {
        return false;
    }

    return inWindow(time, excursion.depart_at ?? '00:00', excursion.return_at);
}
