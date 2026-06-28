import '../css/app.css';
import './bootstrap';

import { registerSW } from 'virtual:pwa-register';
import { createInertiaApp } from '@inertiajs/vue3';

// Register the PWA service worker (no-op in dev). Auto-updates on new releases.
registerSW({ immediate: true });
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';

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
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
