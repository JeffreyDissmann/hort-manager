import { subscribe as pushSubscribe, unsubscribe as pushUnsubscribe } from '@/routes/push';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

// VAPID public key (URL-safe base64) → Uint8Array for the Push API.
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

/** Shared web-push state + subscribe/unsubscribe, used by the toggle and the first-run prompt. */
export function usePush() {
    const page = usePage();
    const vapidPublicKey = page.props.vapidPublicKey;

    const supported = ref(
        'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window &&
            !!vapidPublicKey,
    );
    const subscribed = ref(false);
    const busy = ref(false);

    async function refresh() {
        if (!supported.value) {
            return;
        }
        try {
            const registration = await navigator.serviceWorker.ready;
            subscribed.value = !!(await registration.pushManager.getSubscription());
        } catch {
            // ignore — leave as not subscribed
        }
    }

    async function enable() {
        if (!supported.value) {
            return false;
        }
        busy.value = true;
        try {
            if ((await Notification.requestPermission()) !== 'granted') {
                return false;
            }
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });
            const json = subscription.toJSON();
            await axios.post(pushSubscribe().url, { endpoint: json.endpoint, keys: json.keys });
            subscribed.value = true;
            return true;
        } catch {
            return false;
        } finally {
            busy.value = false;
        }
    }

    async function disable() {
        busy.value = true;
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await axios.delete(pushUnsubscribe().url, { data: { endpoint: subscription.endpoint } });
                await subscription.unsubscribe();
            }
            subscribed.value = false;
        } finally {
            busy.value = false;
        }
    }

    return { supported, subscribed, busy, refresh, enable, disable };
}
