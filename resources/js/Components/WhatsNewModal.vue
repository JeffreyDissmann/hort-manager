<script setup>
import Modal from '@/Components/Modal.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const SEEN_KEY = 'whats-new-seen';

const entry = computed(() => usePage().props.whatsNew);
const show = ref(false);

function open() {
    if (entry.value) {
        show.value = true;
    }
}

function close() {
    if (entry.value) {
        localStorage.setItem(SEEN_KEY, entry.value.version);
    }
    show.value = false;
}

onMounted(() => {
    // Auto-show once per device when the newest entry hasn't been seen yet.
    if (entry.value && localStorage.getItem(SEEN_KEY) !== entry.value.version) {
        show.value = true;
    }
});

defineExpose({ open });
</script>

<template>
    <Modal :show="show" max-width="md" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-hort-navy">✨ Was ist neu?</h2>
            <p v-if="entry" class="mt-1 text-sm text-gray-500">{{ entry.title }}</p>

            <ul v-if="entry" class="mt-4 space-y-3">
                <li
                    v-for="(item, i) in entry.items"
                    :key="i"
                    class="text-sm leading-relaxed text-hort-navy/80"
                >
                    {{ item }}
                </li>
            </ul>

            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    @click="close"
                    class="rounded-lg bg-hort-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-hort-navy-dark"
                >
                    Alles klar
                </button>
            </div>
        </div>
    </Modal>
</template>
