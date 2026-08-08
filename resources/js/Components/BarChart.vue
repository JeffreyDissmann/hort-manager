<script setup>
// A bar chart on Chart.js, themed from the app's own CSS variables so it follows the
// light/dark switch instead of carrying its own palette. Only the pieces a bar chart
// needs are registered — the rest of Chart.js is tree-shaken away.
import { computed } from 'vue';
import { axisStyle, useChartTheme } from '@/charts';
import { Bar } from 'vue-chartjs';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

// The line pieces are here for the percentage curve a bar chart can carry on a second
// axis; everything else Chart.js offers stays out of the bundle.
ChartJS.register(
    BarElement,
    CategoryScale,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

const props = defineProps({
    // [{ label, value }] — already in the order they should appear. For a single
    // series; pass `series` instead when the same label carries several bars.
    bars: { type: Array, default: () => [] },
    // [{ label, color, values: [] }] — grouped bars sharing the labels from `bars`.
    series: { type: Array, default: () => [] },
    // { label, color, values: [] } — a 0–100 % line on its own right-hand axis.
    line: { type: Object, default: null },
    emptyLabel: { type: String, default: '' },
    // Appended to the value in the tooltip, e.g. „Kinder".
    valueSuffix: { type: String, default: '' },
});

const { theme, themeColor } = useChartTheme();

const shape = { borderRadius: 6, borderSkipped: false, maxBarThickness: 56 };

const chartData = computed(() => {
    theme.value; // re-evaluate when the theme flips

    const datasets = props.series.length
        ? props.series.map((serie) => ({
            label: serie.label,
            data: serie.values,
            backgroundColor: themeColor(serie.color, 0.85),
            hoverBackgroundColor: themeColor(serie.color),
            ...shape,
        }))
        : [{
            data: props.bars.map((bar) => bar.value),
            backgroundColor: themeColor('--color-teal', 0.85),
            hoverBackgroundColor: themeColor('--color-teal-dark'),
            ...shape,
        }];

    if (props.line) {
        datasets.unshift({
            type: 'line',
            label: props.line.label,
            data: props.line.values,
            yAxisID: 'percent',
            // `order` is what actually decides the painting order — a lower one draws
            // last, i.e. on top of the bars instead of behind them.
            order: 0,
            borderColor: themeColor(props.line.color),
            backgroundColor: themeColor(props.line.color),
            borderWidth: 3,
            // Points with a ring in the card's own colour, so the line stays legible
            // where it crosses a bar.
            pointRadius: 4,
            pointBorderColor: themeColor('--color-surface'),
            pointBorderWidth: 2,
            pointHoverRadius: 6,
            tension: 0.25,
        });

        datasets.filter((set) => set.type !== 'line').forEach((set) => (set.order = 1));
    }

    return { labels: props.bars.map((bar) => bar.label), datasets };
});

const chartOptions = computed(() => {
    theme.value;
    const ink = (alpha) => themeColor('--color-ink', alpha);

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            // With one dataset a legend just repeats the card's own heading; with
            // several it is the only thing saying which bar is which.
            legend: {
                display: props.series.length > 1 || !!props.line,
                position: 'bottom',
                labels: { color: ink(0.6), boxWidth: 12, boxHeight: 12, usePointStyle: true },
            },
            tooltip: {
                displayColors: props.series.length > 1 || !!props.line,
                callbacks: {
                    label: (item) => {
                        // The line is a percentage, the bars are counts — one tooltip
                        // reading „62" for both would be two different things.
                        if (item.dataset.yAxisID === 'percent') {
                            return `${item.dataset.label}: ${item.formattedValue} %`;
                        }

                        const name = props.series.length > 1 ? `${item.dataset.label}: ` : '';

                        return `${name}${item.formattedValue}${props.valueSuffix ? ` ${props.valueSuffix}` : ''}`;
                    },
                },
            },
        },
        scales: {
            ...axisStyle(themeColor),
            // Fixed 0–100 so the curve means the same thing in every period; its grid
            // is off, or two sets of lines would cross the same plot.
            percent: {
                display: !!props.line,
                position: 'right',
                min: 0,
                max: 100,
                grid: { display: false },
                border: { display: false },
                ticks: {
                    color: ink(0.4),
                    font: { size: 11 },
                    stepSize: 25,
                    callback: (value) => `${value} %`,
                },
            },
        },
    };
});
</script>

<template>
    <p v-if="!bars.length" class="py-8 text-center text-sm text-ink/50">{{ emptyLabel }}</p>

    <div v-else class="h-56">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
