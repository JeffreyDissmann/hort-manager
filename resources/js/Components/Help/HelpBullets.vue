<script setup>
// A list of help points from a translation array — the bullets carry markup
// (<strong>, <em>), so each item is rendered as HTML from our own lang files.
import { tList } from '@/i18n';
import { computed } from 'vue';

const props = defineProps({
    // Translation key of an array of strings, e.g. „help.holidays.care_points".
    itemsKey: { type: String, required: true },
    marker: { type: String, default: '✓' },
    // Steps to follow in order get numbers instead of the marker.
    numbered: { type: Boolean, default: false },
});

const items = computed(() => tList(props.itemsKey));
</script>

<template>
    <ul class="space-y-2">
        <li v-for="(point, i) in items" :key="i" class="flex gap-2 text-sm text-ink/70">
            <span
                v-if="numbered"
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-hort-teal text-[11px] font-bold text-hort-navy"
            >
                {{ i + 1 }}
            </span>
            <span v-else class="shrink-0 text-hort-teal-dark">{{ marker }}</span>
            <span v-html="point" />
        </li>
    </ul>
</template>
