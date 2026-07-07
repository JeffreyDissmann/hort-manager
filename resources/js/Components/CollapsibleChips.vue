<script setup>
// A small disclosure: a link that toggles a wrapped chip list. Each instance owns
// its own open state, so it drops straight into a v-for without any bookkeeping.
import { ref } from 'vue';

defineProps({
    closedLabel: { type: String, required: true },
    openLabel: { type: String, required: true },
});

const open = ref(false);
</script>

<template>
    <div class="mt-3 border-t border-ink/5 pt-3">
        <button
            type="button"
            class="text-sm font-medium text-hort-teal-dark hover:text-ink"
            @click="open = !open"
        >
            {{ open ? openLabel : closedLabel }}
        </button>
        <div v-if="open" class="mt-2 flex flex-wrap gap-1.5">
            <slot />
        </div>
    </div>
</template>
