<script setup>
import { computed } from 'vue';

// A tiny inline trend line (hand-rolled SVG). Stroke is teal when the series ends
// at/above where it started, red when it ends lower.
const props = defineProps({
    points: { type: Array, default: () => [] }, // numbers (e.g. month-end balances)
    width: { type: Number, default: 72 },
    height: { type: Number, default: 22 },
});

const PAD = 2;

const path = computed(() => {
    const pts = props.points;
    if (pts.length < 2) {
        return '';
    }
    const min = Math.min(...pts);
    const max = Math.max(...pts);
    const range = max - min || 1;
    const stepX = (props.width - PAD * 2) / (pts.length - 1);

    return pts
        .map((p, i) => {
            const x = PAD + i * stepX;
            const y = props.height - PAD - ((p - min) / range) * (props.height - PAD * 2);
            return `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .join(' ');
});

const trendUp = computed(() => {
    const pts = props.points;
    return pts.length >= 2 && pts[pts.length - 1] >= pts[0];
});
</script>

<template>
    <svg
        v-if="path"
        :width="width"
        :height="height"
        :viewBox="`0 0 ${width} ${height}`"
        class="shrink-0"
        aria-hidden="true"
    >
        <path
            :d="path"
            fill="none"
            :class="trendUp ? 'stroke-hort-teal-dark' : 'stroke-red-500'"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
    <span v-else class="text-xs text-ink/25">—</span>
</template>
