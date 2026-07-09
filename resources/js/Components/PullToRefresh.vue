<script setup>
// Drag-down-to-refresh, like a social feed. Engages only when the page is scrolled
// to the very top; past the threshold it re-fetches the current Inertia page's data.
// Touch-only — desktop stays on the silent/idle refresh in freshness.js.
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const THRESHOLD = 70; // px of pull needed to trigger a refresh
const MAX_PULL = 110; // clamp so the content can't be dragged too far
const MIN_SPIN_MS = 700; // keep the spinner up this long so the spin is actually seen

const pull = ref(0); // current visual offset in px
const refreshing = ref(false);
const settling = ref(false); // animate back only after release, not during the drag
let startY = null;
let active = false; // the gesture started at the top of the page

// While refreshing the icon spins (animate-spin); while pulling it rotates with the
// drag. The two must not both drive `transform`, or the spin gets frozen.
const iconStyle = computed(() => (refreshing.value ? {} : { transform: `rotate(${pull.value * 3}deg)` }));

function atTop() {
    return window.scrollY <= 0;
}

function onTouchStart(e) {
    if (refreshing.value || e.touches.length !== 1 || !atTop()) {
        active = false;
        return;
    }
    startY = e.touches[0].clientY;
    active = true;
    settling.value = false;
}

function onTouchMove(e) {
    if (!active || startY === null) {
        return;
    }
    const delta = e.touches[0].clientY - startY;
    if (delta <= 0 || !atTop()) {
        pull.value = 0;
        return;
    }
    // Damped + clamped, so the pull feels resistant like a native one.
    pull.value = Math.min(MAX_PULL, delta * 0.5);
    if (pull.value > 0 && e.cancelable) {
        e.preventDefault(); // stop the page scrolling while we pull
    }
}

function onTouchEnd() {
    if (!active) {
        return;
    }
    active = false;
    startY = null;
    settling.value = true;
    if (pull.value >= THRESHOLD) {
        trigger();
    } else {
        pull.value = 0;
    }
}

function trigger() {
    refreshing.value = true;
    pull.value = THRESHOLD; // hold the spinner in view while it loads
    const startedAt = Date.now();
    router.reload({
        onFinish: () => {
            // A local reload can finish in ~100ms; keep spinning briefly so the
            // gesture reads as "refreshing" rather than a flicker.
            const wait = Math.max(0, MIN_SPIN_MS - (Date.now() - startedAt));
            window.setTimeout(() => {
                refreshing.value = false;
                pull.value = 0;
            }, wait);
        },
    });
}
</script>

<template>
    <div
        class="relative"
        @touchstart.passive="onTouchStart"
        @touchmove="onTouchMove"
        @touchend="onTouchEnd"
        @touchcancel="onTouchEnd"
    >
        <!-- Pull indicator: follows the drag at half speed, staying centered in the
             opening gap; fades/rotates in as you drag, spins while refreshing. -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 z-30 flex justify-center"
            :style="{ transform: `translateY(${pull / 2 - 18}px)`, opacity: Math.min(1, pull / THRESHOLD) }"
        >
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-surface shadow-md ring-1 ring-ink/5">
                <ArrowPathIcon
                    class="h-5 w-5 text-hort-teal-dark"
                    :class="{ 'animate-spin': refreshing }"
                    :style="iconStyle"
                />
            </span>
        </div>

        <div :style="{ transform: `translateY(${pull}px)`, transition: settling ? 'transform 0.2s ease-out' : 'none' }">
            <slot />
        </div>
    </div>
</template>
