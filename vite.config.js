import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder(),
        VitePWA({
            registerType: 'autoUpdate',
            scope: '/',
            // We register the SW ourselves (served from the site root at /sw.js).
            injectRegister: null,
            // injectManifest so we can add custom push/notificationclick handlers
            // (resources/js/sw.js) while Workbox still precaches the built assets.
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.js',
            injectManifest: {
                globPatterns: ['**/*.{js,css,woff2}'],
                // Assets are served under /build/, but the SW lives at the root (/sw.js),
                // so precache URLs need the /build/ prefix to resolve.
                modifyURLPrefix: { '': '/build/' },
            },
            manifest: {
                name: 'Hort-Manager',
                short_name: 'Hort',
                description:
                    'Wann und wie geht jedes Kind nach Hause – plus Ausflüge.',
                lang: 'de',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                orientation: 'portrait',
                background_color: '#F7F5F0',
                theme_color: '#223E55',
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                    {
                        src: '/icons/icon-maskable-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
        }),
    ],
});
