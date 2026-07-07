<script setup>
import { usePush } from '@/composables/usePush';
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

// Ask once, the first time the installed app is opened.
const ASKED_KEY = 'push-prompted';

const { supported, subscribed, busy, refresh, enable } = usePush();
const visible = ref(false);

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

onMounted(async () => {
    const user = usePage().props.auth?.user;

    if (
        !supported.value ||
        !isStandalone() || // only inside the installed app
        user?.role !== 'parent' || // only parents receive pushes
        localStorage.getItem(ASKED_KEY) || // already asked once
        Notification.permission !== 'default' // already granted/denied
    ) {
        return;
    }

    await refresh();
    if (!subscribed.value) {
        visible.value = true;
    }
});

async function accept() {
    localStorage.setItem(ASKED_KEY, '1');
    await enable();
    visible.value = false;
}

function decline() {
    localStorage.setItem(ASKED_KEY, '1');
    visible.value = false;
}
</script>

<template>
    <div v-if="visible" class="border-b border-ink/10 bg-hort-teal/15">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3">
            <span class="text-lg">🔔</span>
            <p class="flex-1 text-sm text-ink">
                {{ $t('components.notify.prompt') }}
            </p>
            <button
                type="button"
                :disabled="busy"
                @click="accept"
                class="shrink-0 rounded-lg bg-hort-navy px-3 py-1 text-sm font-semibold text-white transition hover:bg-hort-navy-dark disabled:opacity-50"
            >
                {{ $t('components.notify.accept') }}
            </button>
            <button
                type="button"
                @click="decline"
                class="shrink-0 rounded-lg px-3 py-1 text-sm text-ink/70 transition hover:text-ink"
            >
                {{ $t('components.notify.decline') }}
            </button>
        </div>
    </div>
</template>
