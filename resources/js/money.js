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
