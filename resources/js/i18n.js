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

/** Vue plugin: exposes `$t` in every template. */
export const i18n = {
    install(app) {
        app.config.globalProperties.$t = t;
    },
};
