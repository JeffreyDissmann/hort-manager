// Service worker for the Hort-Manager PWA.
// vite-plugin-pwa (injectManifest) replaces self.__WB_MANIFEST with the list of
// built assets to precache. Push / notificationclick handlers are added later.

const PRECACHE = 'hort-precache-v1';
const ASSETS = (self.__WB_MANIFEST || []).map((entry) => entry.url);

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(PRECACHE)
            // Cache what we can; a single un-fetchable asset must not abort the
            // install (which would stop the worker activating — and break push).
            .then((cache) => Promise.allSettled(ASSETS.map((url) => cache.add(url))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys.filter((key) => key !== PRECACHE).map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    // Cache-first for precached build assets; everything else goes to the network
    // (this is a live app — no offline navigation fallback).
    event.respondWith(caches.match(event.request).then((hit) => hit || fetch(event.request)));
});

// ── Web push ─────────────────────────────────────────────────────────────────

self.addEventListener('push', (event) => {
    const payload = (() => {
        try {
            return event.data ? event.data.json() : {};
        } catch {
            return {};
        }
    })();

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Hort-Manager', {
            body: payload.body || '',
            icon: payload.icon || '/icons/icon-192.png',
            badge: payload.badge || '/icons/icon-192.png',
            data: payload.data || {},
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            // Focus an open window (navigating it), otherwise open a new one.
            for (const client of windows) {
                if ('focus' in client) {
                    client.focus();
                    if ('navigate' in client) {
                        client.navigate(url);
                    }
                    return undefined;
                }
            }
            return self.clients.openWindow(url);
        }),
    );
});
