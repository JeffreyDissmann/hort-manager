import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { i18n } from './i18n';
import { initTheme } from './theme';
import { initFreshness } from './freshness';

// Keep the runtime theme (OS-change listener + PWA colour) in sync; the pre-paint
// `.dark` class is already set by the inline script in app.blade.php.
initTheme();

// Register the PWA service worker (served from the site root for "/" scope) and
// keep the installed app fresh: silent reload on a new deploy + a stale-content
// refresh after a long time in the background. Run immediately (not on `load`):
// this module is deferred, so `load` may already have fired.
initFreshness();

const appName = import.meta.env.VITE_APP_NAME || 'Hort-Manager';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
