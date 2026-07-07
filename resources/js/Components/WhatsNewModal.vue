<script setup>
import Modal from '@/Components/Modal.vue';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const SEEN_KEY = 'whats-new-seen';

const entries = computed(() => usePage().props.whatsNew ?? []);
const show = ref(false);
const index = ref(0); // 0 = newest
const entry = computed(() => entries.value[index.value] ?? null);

function open() {
    if (entries.value.length) {
        index.value = 0;
        show.value = true;
    }
}

function close() {
    if (entries.value.length) {
        localStorage.setItem(SEEN_KEY, entries.value[0].version);
    }
    show.value = false;
}

// Higher index = older entry.
function older() {
    if (index.value < entries.value.length - 1) {
        index.value++;
    }
}
function newer() {
    if (index.value > 0) {
        index.value--;
    }
}

function formatDate(iso) {
    if (!iso) {
        return '';
    }
    const [y, m, d] = iso.split('-');
    return `${d}.${m}.${y}`;
}

onMounted(() => {
    // Auto-show the newest entry once per device when it hasn't been seen.
    if (entries.value.length && localStorage.getItem(SEEN_KEY) !== entries.value[0].version) {
        show.value = true;
    }
});

defineExpose({ open });
</script>

<template>
    <Modal :show="show" max-width="md" @close="close">
        <div v-if="entry" class="p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-ink">{{ $t('components.whats_new.title') }}</h2>
                <span v-if="entries.length > 1" class="text-xs text-ink/40">
                    {{ index + 1 }}/{{ entries.length }}
                </span>
            </div>

            <p class="mt-1 text-sm text-ink/60">
                {{ entry.title }}
                <span v-if="entry.date" class="text-ink/40">· {{ formatDate(entry.date) }}</span>
            </p>

            <ul class="mt-4 space-y-3">
                <li
                    v-for="(item, i) in entry.items"
                    :key="i"
                    class="text-sm leading-relaxed text-ink/80"
                >
                    {{ item }}
                </li>
            </ul>

            <div class="mt-6 flex items-center justify-between">
                <div v-if="entries.length > 1" class="flex gap-1">
                    <button
                        type="button"
                        :disabled="index >= entries.length - 1"
                        @click="older"
                        :aria-label="$t('components.whats_new.older')"
                        class="rounded-lg border border-ink/10 p-1.5 text-ink transition hover:bg-canvas disabled:opacity-30"
                    >
                        <ChevronLeftIcon class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        :disabled="index <= 0"
                        @click="newer"
                        :aria-label="$t('components.whats_new.newer')"
                        class="rounded-lg border border-ink/10 p-1.5 text-ink transition hover:bg-canvas disabled:opacity-30"
                    >
                        <ChevronRightIcon class="h-5 w-5" />
                    </button>
                </div>
                <span v-else></span>

                <button
                    type="button"
                    @click="close"
                    class="rounded-lg bg-hort-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-hort-navy-dark"
                >
                    {{ $t('components.whats_new.dismiss') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
