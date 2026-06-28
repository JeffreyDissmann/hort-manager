// Service worker for the Hort-Manager PWA.
// vite-plugin-pwa (injectManifest) replaces self.__WB_MANIFEST with the list of
// built assets to precache. Push / notificationclick handlers are added later.

const PRECACHE = 'hort-precache-v1';
const ASSETS = (self.__WB_MANIFEST || []).map((entry) => entry.url);

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(PRECACHE)
            .then((cache) => cache.addAll(ASSETS))
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
