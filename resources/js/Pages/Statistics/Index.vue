<script setup>
// „Statistik" — what the Hort's own records add up to. Admin-only; every number here
// is an aggregate, so no single child is ever named.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BarChart from '@/Components/BarChart.vue';
import { statistics } from '@/routes';
import { t } from '@/i18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    range: { type: String, default: 'quarter' },
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    // [{ time, count }] — pickups per half-hour slot.
    pickupTimes: { type: Array, default: () => [] },
    // [{ month, sick, away }] — one entry per month of the range.
    absences: { type: Array, default: () => [] },
});

const locale = computed(() => usePage().props.locale || 'de');

const ranges = ['quarter', 'school-year', 'year'];

function dateLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

// A null time is the „gar nicht da" bucket the server puts in front of the real slots.
const bars = computed(() =>
    props.pickupTimes.map((slot) => ({
        label: slot.time ?? t('statistics.pickup_absent'),
        value: slot.count,
    })),
);

// How many are still there after each slot. Starts below 100 exactly by the share who
// never came, and ends at 0 — that's the whole point of the curve.
// Orange-dark, not navy: navy stays dark in dark mode (it is chrome and on-accent
// text), so the curve would disappear into the card. This token brightens instead.
const stillHere = computed(() => ({
    label: t('statistics.pickup_remaining'),
    color: '--color-orange-dark',
    values: props.pickupTimes.map((slot) => slot.remaining),
}));

/** „2026-02" → „Feb 26" — short enough that twelve of them fit on a phone. */
function monthLabel(month) {
    const [year, index] = month.split('-');

    return new Date(Number(year), Number(index) - 1, 1).toLocaleDateString(locale.value, {
        month: 'short',
        year: '2-digit',
    });
}

const absenceMonths = computed(() =>
    props.absences.map((month) => ({ label: monthLabel(month.month), value: month.sick + month.away })),
);

// Two bars per month, not one stacked total: a February flu wave and a family's own
// appointment are different things, and only the split shows which one a month was.
const absenceSeries = computed(() => [
    {
        label: t('statistics.absence_sick'),
        color: '--color-orange',
        values: props.absences.map((month) => month.sick),
    },
    {
        label: t('statistics.absence_away'),
        color: '--color-purple',
        values: props.absences.map((month) => month.away),
    },
]);

const hasAbsences = computed(() => props.absences.some((month) => month.sick + month.away > 0));
</script>

<template>
    <Head :title="$t('statistics.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-ink">{{ $t('statistics.title') }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- One range drives every chart below, so it lives above all of them. -->
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-for="option in ranges"
                        :key="option"
                        :href="statistics({ query: { range: option } }).url"
                        :data-testid="`range-${option}`"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="range === option
                            ? 'bg-hort-teal/20 text-ink'
                            : 'text-ink/60 hover:bg-ink/5 hover:text-ink'"
                    >
                        {{ $t(`statistics.range.${option}`) }}
                    </Link>
                    <span class="ml-auto text-xs text-ink/40">
                        {{ dateLabel(from) }} – {{ dateLabel(to) }}
                    </span>
                </div>

                <section class="rounded-2xl bg-surface p-4 shadow-sm sm:p-6">
                    <h3 class="font-semibold text-ink">{{ $t('statistics.pickup_times_title') }}</h3>
                    <p class="mt-0.5 text-sm text-ink/60">{{ $t('statistics.pickup_times_intro') }}</p>

                    <div class="mt-4" data-testid="pickup-times">
                        <BarChart
                            :bars="bars"
                            :line="stillHere"
                            :empty-label="$t('statistics.empty')"
                        />
                    </div>
                </section>

                <section class="rounded-2xl bg-surface p-4 shadow-sm sm:p-6">
                    <h3 class="font-semibold text-ink">{{ $t('statistics.absences_title') }}</h3>
                    <p class="mt-0.5 text-sm text-ink/60">{{ $t('statistics.absences_intro') }}</p>

                    <div class="mt-4" data-testid="absences">
                        <BarChart
                            :bars="hasAbsences ? absenceMonths : []"
                            :series="absenceSeries"
                            :empty-label="$t('statistics.absences_empty')"
                        />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
