<script setup>
// A trend over time. Same palette handling as BarChart (see @/charts) — a line rather
// than bars because the question is „how does it move", not „compare these buckets".
import { computed } from 'vue';
import { axisStyle, useChartTheme } from '@/charts';
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

ChartJS.register(CategoryScale, Filler, LinearScale, LineElement, PointElement, Tooltip);

const props = defineProps({
    // [{ label, value }] — in the order they should appear along the x axis.
    points: { type: Array, default: () => [] },
    emptyLabel: { type: String, default: '' },
    // Appended to the value in the tooltip, e.g. „Kinder".
    valueSuffix: { type: String, default: '' },
});

const { theme, themeColor } = useChartTheme();

const chartData = computed(() => {
    theme.value; // re-evaluate when the theme flips

    return {
        labels: props.points.map((point) => point.label),
        datasets: [{
            data: props.points.map((point) => point.value),
            borderColor: themeColor('--color-teal-dark'),
            backgroundColor: themeColor('--color-teal', 0.15),
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            // A year is ~50 points: dots would turn the line into a caterpillar. They
            // come back on hover, where they mark what the tooltip is talking about.
            pointRadius: props.points.length > 20 ? 0 : 3,
            pointHoverRadius: 5,
        }],
    };
});

const chartOptions = computed(() => {
    theme.value;

    return {
        responsive: true,
        maintainAspectRatio: false,
        // Hovering anywhere on the column finds the point — on a phone, hitting a
        // 2-pixel line with a fingertip is not a plan.
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                displayColors: false,
                callbacks: {
                    label: (item) => `${item.formattedValue}${props.valueSuffix ? ` ${props.valueSuffix}` : ''}`,
                },
            },
        },
        scales: axisStyle(themeColor),
    };
});
</script>

<template>
    <p v-if="!points.length" class="py-8 text-center text-sm text-ink/50">{{ emptyLabel }}</p>

    <div v-else class="h-56">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
