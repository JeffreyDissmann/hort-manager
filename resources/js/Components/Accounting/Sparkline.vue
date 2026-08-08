<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    LinearScale,
    LineElement,
    PointElement,
} from 'chart.js';
import { useChartTheme } from '@/charts';

ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement);

// A tiny inline trend line: no axes, no grid, no tooltip — at 72×22 there is room for
// the shape and nothing else. Teal when the series ends at or above where it started,
// red when it ends lower, so a falling balance reads at a glance.
const props = defineProps({
    points: { type: Array, default: () => [] }, // numbers (e.g. month-end balances)
    width: { type: Number, default: 72 },
    height: { type: Number, default: 22 },
});

const { theme, themeColor } = useChartTheme();

const trendUp = computed(() => {
    const pts = props.points;

    return pts.length >= 2 && pts[pts.length - 1] >= pts[0];
});

const chartData = computed(() => {
    theme.value; // re-evaluate when the theme flips

    return {
        labels: props.points.map((_, index) => index),
        datasets: [{
            data: props.points,
            borderColor: trendUp.value ? themeColor('--color-teal-dark') : '#ef4444',
            borderWidth: 1.5,
            pointRadius: 0,
            tension: 0.3,
        }],
    };
});

// Fixed size rather than responsive: this lives in a table cell, where a chart that
// measures its container would fight the column width on every render.
const chartOptions = {
    responsive: false,
    animation: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
    scales: { x: { display: false }, y: { display: false } },
    // The stroke would otherwise be clipped at the top and bottom of the box.
    layout: { padding: 2 },
    elements: { line: { capBezierPoints: true } },
};
</script>

<template>
    <Line
        v-if="points.length >= 2"
        :data="chartData"
        :options="chartOptions"
        :width="width"
        :height="height"
        class="shrink-0"
        aria-hidden="true"
    />
    <span v-else class="text-xs text-ink/25">—</span>
</template>
