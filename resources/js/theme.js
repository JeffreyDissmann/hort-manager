import { ref } from 'vue';

// Per-device theme preference (no server round-trip). One of: 'system' | 'light' | 'dark'.
// 'system' follows the OS via `prefers-color-scheme`. The actual `.dark` class is applied
// pre-paint by an inline script in app.blade.php; this module keeps runtime changes and the
// OS listener in sync, and backs the switcher UI.
const STORAGE_KEY = 'theme';
const THEMES = ['system', 'light', 'dark'];

// PWA/browser chrome colour per resolved scheme (mirrors --color-canvas-ish framing).
const META_LIGHT = '#223E55';
const META_DARK = '#0f172a';

export const preference = ref(readStored());

function readStored() {
    const stored = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;
    return THEMES.includes(stored) ? stored : 'system';
}

function systemPrefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolvedDark(pref = preference.value) {
    return pref === 'dark' || (pref === 'system' && systemPrefersDark());
}

/** Apply the current preference to the document (toggles `.dark` + PWA theme colour). */
export function applyTheme() {
    const dark = resolvedDark();
    document.documentElement.classList.toggle('dark', dark);
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', dark ? META_DARK : META_LIGHT);
    }
}

/** Persist and apply a new preference. */
export function setTheme(pref) {
    if (!THEMES.includes(pref)) {
        return;
    }
    preference.value = pref;
    localStorage.setItem(STORAGE_KEY, pref);
    applyTheme();
}

/** Wire up the OS-change listener once at boot; re-applies only while following the system. */
export function initTheme() {
    applyTheme();
    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            if (preference.value === 'system') {
                applyTheme();
            }
        });
}
