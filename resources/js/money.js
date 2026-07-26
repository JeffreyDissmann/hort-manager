// Money is stored and passed around as integer cents. Format for display in
// German locale („1.234,56 €") — the Hort books in euros regardless of UI language.
const euroFormatter = new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
});

/** Format integer cents as a euro string, e.g. 123456 → „1.234,56 €". */
export function formatEuro(cents) {
    return euroFormatter.format((cents ?? 0) / 100);
}

// Whole euros, no cents — for tight spots like a donut's centre, e.g. 123456 → „1.235 €".
const euroFormatterShort = new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

/** Format integer cents as a euro string without cents, e.g. 123456 → „1.235 €". */
export function formatEuroShort(cents) {
    return euroFormatterShort.format(Math.round((cents ?? 0) / 100));
}
