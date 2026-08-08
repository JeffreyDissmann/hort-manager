<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { ArcElement, Chart as ChartJS, Tooltip } from 'chart.js';
import { useChartTheme } from '@/charts';
import { formatEuroShort } from '@/money';
import { t } from '@/i18n';

ChartJS.register(ArcElement, Tooltip);

// A donut with a compact legend of its own — Chart.js's legend can only show labels,
// and every row here carries a share and a real amount as well. Segment sizes use the
// magnitude (expense totals are negative); the legend shows the signed value. The long
// tail folds into „Sonstige" so the legend stays small and the donut isn't a mess of
// slivers. Clicking a slice or a legend row emits `select` with the segment id.
const props = defineProps({
    title: { type: String, default: '' },
    // [{ id, label, value }] — value may be signed.
    segments: { type: Array, default: () => [] },
    emptyLabel: { type: String, default: '' },
    // Show at most this many rows; the rest fold into „Sonstige".
    maxSlices: { type: Number, default: 6 },
});
const emit = defineEmits(['select']);

// Validated categorical palette (dataviz skill, light mode) + a neutral for „Sonstige".
const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
const OTHER_COLOR = '#94a3b8';

const { theme, themeColor } = useChartTheme();

const totalValue = computed(() => props.segments.reduce((sum, s) => sum + s.value, 0));

const items = computed(() => {
    const list = props.segments
        .map((s) => ({ ...s, abs: Math.abs(s.value) }))
        .filter((s) => s.abs > 0)
        .sort((a, b) => b.abs - a.abs);

    // Fold everything past the cap into a single „Sonstige" row.
    let rows = list;
    if (list.length > props.maxSlices) {
        const head = list.slice(0, props.maxSlices - 1);
        const tail = list.slice(props.maxSlices - 1);
        head.push({
            id: '__other',
            label: t('accounting.reports.other'),
            value: tail.reduce((sum, s) => sum + s.value, 0),
            abs: tail.reduce((sum, s) => sum + s.abs, 0),
            isOther: true,
        });
        rows = head;
    }

    const total = rows.reduce((sum, s) => sum + s.abs, 0);

    return rows.map((s, i) => ({
        ...s,
        color: s.isOther ? OTHER_COLOR : PALETTE[i % PALETTE.length],
        pct: total > 0 ? Math.round((s.abs / total) * 100) : 0,
    }));
});

const chartData = computed(() => {
    theme.value; // re-evaluate when the theme flips

    return {
        labels: items.value.map((it) => it.label),
        datasets: [{
            data: items.value.map((it) => it.abs),
            backgroundColor: items.value.map((it) => it.color),
            // The ring reads as separate slices without a stroke of its own colour.
            borderColor: themeColor('--color-surface'),
            borderWidth: 2,
            hoverOffset: 4,
        }],
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
        legend: { display: false },
        tooltip: {
            displayColors: false,
            callbacks: {
                label: (item) => {
                    const it = items.value[item.dataIndex];

                    return `${formatEuroShort(it.value)} · ${it.pct}%`;
                },
            },
        },
    },
    // „Sonstige" is several categories at once, so there is nothing to select.
    onClick: (event, elements) => {
        const it = items.value[elements[0]?.index];

        if (it && ! it.isOther) {
            emit('select', it.id);
        }
    },
}));
</script>

<template>
    <div class="flex h-full flex-col rounded-2xl bg-surface p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-ink">{{ title }}</h3>

        <p v-if="!items.length" class="py-10 text-center text-sm text-ink/40">{{ emptyLabel }}</p>

        <!-- flex-1 fills the card below the title; items-center vertically centres the donut -->
        <div v-else class="flex flex-1 items-center gap-5">
            <div class="relative h-28 w-28 shrink-0">
                <Doughnut :data="chartData" :options="chartOptions" />
                <!-- Total in the hole — whole euros so it fits. -->
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-[10px] uppercase tracking-wide text-ink/40">Σ</span>
                    <span class="text-xs font-semibold tabular-nums text-ink">{{ formatEuroShort(totalValue) }}</span>
                </div>
            </div>

            <ul class="min-w-0 flex-1 space-y-0.5 text-sm">
                <li v-for="it in items" :key="it.id">
                    <button
                        type="button"
                        class="flex w-full min-w-0 items-center gap-2 rounded px-1 py-1 text-left transition hover:bg-ink/5"
                        :disabled="it.isOther"
                        :class="{ 'cursor-default': it.isOther }"
                        @click="!it.isOther && emit('select', it.id)"
                    >
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: it.color }" />
                        <span class="min-w-0 flex-1 truncate text-ink/80">{{ it.label }}</span>
                        <span class="w-8 shrink-0 text-right tabular-nums text-ink/40">{{ it.pct }}%</span>
                        <span class="shrink-0 tabular-nums font-medium text-ink">{{ formatEuroShort(it.value) }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
