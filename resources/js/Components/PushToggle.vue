<script setup>
import { subscribe as pushSubscribe, unsubscribe as pushUnsubscribe } from '@/routes/push';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const supported = ref(false);
const subscribed = ref(false);
const busy = ref(false);

const vapidPublicKey = computed(() => usePage().props.vapidPublicKey);

onMounted(async () => {
    supported.value =
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window &&
        !!vapidPublicKey.value;

    if (!supported.value) {
        return;
    }

    try {
        const registration = await navigator.serviceWorker.ready;
        subscribed.value = !!(await registration.pushManager.getSubscription());
    } catch {
        // ignore — leave as not subscribed
    }
});

// VAPID public key (URL-safe base64) → Uint8Array for the Push API.
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

async function enable() {
    busy.value = true;
    try {
        if ((await Notification.requestPermission()) !== 'granted') {
            return;
        }
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey.value),
        });
        const json = subscription.toJSON();
        await axios.post(pushSubscribe().url, { endpoint: json.endpoint, keys: json.keys });
        subscribed.value = true;
    } catch {
        // permission denied or subscribe failed
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
</script>

<template>
    <button
        v-if="supported"
        type="button"
        :disabled="busy"
        @click="subscribed ? disable() : enable()"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-hort-navy transition duration-150 ease-in-out hover:bg-hort-sand focus:bg-hort-sand focus:outline-none disabled:opacity-50"
    >
        {{ subscribed ? '🔕 Benachrichtigungen aus' : '🔔 Benachrichtigungen an' }}
    </button>
</template>
