<script setup>
import { computed } from 'vue';

// Renders a parent's Slack profile picture, falling back to their initial.
const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '' },
    sizeClass: { type: String, default: 'h-8 w-8 text-sm' },
});

const initial = computed(() => props.name.charAt(0).toUpperCase());
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="name"
        :class="sizeClass"
        class="shrink-0 rounded-full object-cover"
        referrerpolicy="no-referrer"
    />
    <span
        v-else
        :class="sizeClass"
        class="flex shrink-0 items-center justify-center rounded-full bg-hort-teal font-semibold text-hort-navy"
    >
        {{ initial }}
    </span>
</template>
