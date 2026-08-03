// Whether a child's enrolment period overlaps a given calendar year. Dates are
// plain `YYYY-MM-DD` strings, so lexical comparison is date comparison.
export function activeInYear(child, year) {
    if (!year) {
        return true;
    }
    const from = child.active_from ?? '0000-01-01';
    return from <= `${year}-12-31` && (child.active_until == null || child.active_until >= `${year}-01-01`);
}

/** The year of a `YYYY-MM-DD` date string, or null. */
export function yearOf(date) {
    return date ? Number(String(date).slice(0, 4)) : null;
}
