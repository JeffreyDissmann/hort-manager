// Keeps the installed PWA feeling live without a manual refresh:
//   1. Silent update — when a new service worker takes over, reload once so the
//      running page swaps to the newly-deployed assets (no prompt).
//   2. Stale-content refresh — if the app was in the background (or idle) for
//      15+ minutes, quietly re-fetch the current page's data when it returns to
//      the foreground.
// Pull-to-refresh (the drag-down gesture) lives in PullToRefresh.vue.
import { router } from '@inertiajs/vue3';

// How long the app may sit backgrounded/idle before we refresh its data on return.
const IDLE_MS = 15 * 60 * 1000; // 15 minutes
let lastActiveAt = Date.now();

function markActive() {
    lastActiveAt = Date.now();
}

// Never clobber an in-progress edit: an open modal (native <dialog>) or a focused
// text field means the user is mid-task, so hold the auto-refresh.
function isBusyEditing() {
    if (document.querySelector('dialog[open]')) {
        return true;
    }
    const el = document.activeElement;
    return !!el && (
        el.tagName === 'INPUT'
        || el.tagName === 'TEXTAREA'
        || el.tagName === 'SELECT'
        || el.isContentEditable
    );
}

/** Re-fetch the current Inertia page's data in place (used by idle-refresh + pull). */
export function refreshContent() {
    if (isBusyEditing()) {
        return;
    }
    router.reload();
}

export function initFreshness() {
    let reloading = false;
    let pendingSwReload = false; // a new deploy landed while the user was mid-edit

    function reloadForUpdate() {
        if (reloading) {
            return;
        }
        // Don't reload out from under an in-progress edit — defer until they're idle.
        if (isBusyEditing()) {
            pendingSwReload = true;
            return;
        }
        reloading = true;
        window.location.reload();
    }

    if ('serviceWorker' in navigator) {
        // Only arm the reload when a worker is ALREADY controlling this page: the
        // very first install also fires `controllerchange` (via clients.claim()),
        // and we don't want to reload on first visit — only on genuine updates.
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.addEventListener('controllerchange', reloadForUpdate);
        }

        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                // Check for a fresh worker each time the app returns to the foreground.
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        registration.update();
                    }
                });
            })
            .catch((error) => console.error('Service worker registration failed:', error));
    }

    // Track real user activity so "idle" reflects interaction, not just tab focus.
    ['pointerdown', 'keydown', 'touchstart'].forEach((event) => {
        window.addEventListener(event, markActive, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') {
            return;
        }
        // A deploy that arrived mid-edit takes priority once the user is back and idle.
        if (pendingSwReload) {
            reloadForUpdate();
            return;
        }
        if (Date.now() - lastActiveAt >= IDLE_MS) {
            refreshContent();
        }
        markActive();
    });
}
