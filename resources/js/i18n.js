import { usePage } from '@inertiajs/vue3';

/**
 * Translate a dot-notation key ("nav.today") using the message catalog shared by
 * the server for the active locale. Unknown keys return the key itself (so a
 * missing translation is visible, not blank). `:name`-style placeholders are
 * replaced from `replacements`.
 */
export function t(key, replacements = {}) {
    const messages = usePage().props.translations ?? {};

    let value = key
        .split('.')
        .reduce((carry, part) => (carry == null ? undefined : carry[part]), messages);

    if (typeof value !== 'string') {
        return key;
    }

    for (const [token, replacement] of Object.entries(replacements)) {
        value = value.replaceAll(`:${token}`, String(replacement));
    }

    return value;
}

/**
 * Translate a key that holds a *list* of strings (help bullets, examples …).
 * Returns an empty array for a missing key, so a template can render nothing
 * rather than the key itself — and callers don't hardcode how many items there are.
 */
export function tList(key) {
    const messages = usePage().props.translations ?? {};

    const value = key
        .split('.')
        .reduce((carry, part) => (carry == null ? undefined : carry[part]), messages);

    return Array.isArray(value) ? value : [];
}

/** Vue plugin: exposes `$t` in every template. */
export const i18n = {
    install(app) {
        app.config.globalProperties.$t = t;
    },
};
