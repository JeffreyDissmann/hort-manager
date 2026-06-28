<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const DISMISS_KEY = 'pwa-install-dismissed';

const visible = ref(false);
const iosHint = ref(false);
let deferredPrompt = null;

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function isIos() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

function dismissed() {
    return localStorage.getItem(DISMISS_KEY) === '1';
}

function onBeforeInstallPrompt(event) {
    event.preventDefault();
    deferredPrompt = event;
    if (!dismissed()) {
        iosHint.value = false;
        visible.value = true;
    }
}

function onAppInstalled() {
    deferredPrompt = null;
    visible.value = false;
}

onMounted(() => {
    // Already installed (running standalone) or dismissed → never show.
    if (isStandalone() || dismissed()) {
        return;
    }

    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onAppInstalled);

    // iOS Safari has no install prompt event — show a short manual hint instead.
    if (isIos()) {
        iosHint.value = true;
        visible.value = true;
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.removeEventListener('appinstalled', onAppInstalled);
});

async function install() {
    if (!deferredPrompt) {
        return;
    }
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    visible.value = false;
}

function dismiss() {
    localStorage.setItem(DISMISS_KEY, '1');
    visible.value = false;
}
</script>

<template>
    <div v-if="visible" class="bg-hort-navy text-sm text-white">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2">
            <span class="text-lg">📲</span>
            <p class="flex-1">
                <template v-if="iosHint">
                    Tipp: über <strong>Teilen → Zum Home-Bildschirm</strong> kannst du den
                    Hort-Manager als App installieren.
                </template>
                <template v-else>
                    Installiere den Hort-Manager als App – für schnellen Zugriff und
                    Benachrichtigungen.
                </template>
            </p>
            <button
                v-if="!iosHint"
                type="button"
                @click="install"
                class="shrink-0 rounded-lg bg-hort-teal px-3 py-1 font-semibold text-hort-navy transition hover:bg-hort-teal-dark"
            >
                Installieren
            </button>
            <button
                type="button"
                @click="dismiss"
                class="shrink-0 rounded-lg px-2 py-1 text-white/70 transition hover:text-white"
                aria-label="Schließen"
            >
                ✕
            </button>
        </div>
    </div>
</template>
