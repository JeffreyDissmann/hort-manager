<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { formatEuro } from '@/money';
import { index as contributionsIndex } from '@/routes/accounting/contributions';
import { index as bookingsIndex } from '@/routes/accounting/bookings';

const props = defineProps({
    roots: { type: Array, required: true },              // top-level income categories
    root: { type: Number, required: true },              // selected group
    year: { type: Number, required: true },
    years: { type: Array, required: true },
    monthLabels: { type: Array, required: true },
    rows: { type: Array, required: true },
    inactiveRows: { type: Array, default: () => [] }, // attributed to a child not enrolled this year
    monthTotals: { type: Array, required: true },
    grandTotal: { type: Number, required: true },
    unassignedMonths: { type: Array, required: true },
    unassignedTotal: { type: Number, required: true },
    unassignedCategoryId: { type: [Number, String], default: null },
    pastMonths: { type: Number, required: true },
});

function reload(next) {
    const query = { year: next.year ?? props.year, root: next.root ?? props.root };
    router.get(contributionsIndex({ query }).url, {}, { preserveScroll: true });
}

// Deep-link to the bookings list showing the misassigned (child-less) income to fix.
const unassignedLink = computed(() => {
    const query = { status: 'confirmed', unassigned: 1 };
    if (props.unassignedCategoryId) {
        query.category = props.unassignedCategoryId;
    }
    return bookingsIndex({ query }).url;
});

// A zero cell counts as „missing" in an already-past month (used on category rows,
// and on the child row itself when a single stream is selected).
const isMissing = (cents, monthIndex) => cents === 0 && monthIndex + 1 <= props.pastMonths;
const cell = (cents) => (cents === 0 ? '—' : formatEuro(cents));
const valueClass = (cents) => (cents === 0 ? 'text-ink/25' : 'text-hort-teal-dark');
const missingClass = 'bg-red-500/10 text-red-600';
</script>

<template>
    <Head :title="$t('accounting.contributions.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
                    <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.contributions.title') }}</h2>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-ink/60">
                        {{ $t('accounting.contributions.group') }}
                        <select
                            :value="root"
                            data-testid="contribution-group"
                            class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal"
                            @change="reload({ root: Number($event.target.value) })"
                        >
                            <option v-for="r in roots" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-ink/60">
                        {{ $t('accounting.contributions.year') }}
                        <select
                            :value="year"
                            data-testid="contribution-year"
                            class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal"
                            @change="reload({ year: $event.target.value })"
                        >
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </label>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <p class="text-sm text-ink/50">
                {{ $t('accounting.contributions.intro') }}
                <span class="text-red-600/70">{{ $t('accounting.contributions.missing_hint') }}</span>
            </p>

            <div class="overflow-hidden rounded-2xl bg-surface shadow-sm">
                <p v-if="!rows.length && !inactiveRows.length" class="p-6 text-center text-ink/50">{{ $t('accounting.contributions.empty') }}</p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="border-b border-ink/10 text-xs uppercase tracking-wide text-ink/40">
                            <tr>
                                <th class="sticky left-0 z-10 bg-surface px-3 py-2 text-left font-medium">
                                    {{ $t('accounting.contributions.child') }}
                                </th>
                                <th v-for="(label, i) in monthLabels" :key="i" class="px-3 py-2 text-right font-medium">
                                    {{ label }}
                                </th>
                                <th class="px-3 py-2 text-right font-semibold">{{ $t('accounting.contributions.total') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-ink/5">
                            <template v-for="row in rows" :key="row.id">
                                <tr class="hover:bg-ink/5">
                                    <td class="sticky left-0 z-10 bg-surface px-3 py-1.5 font-medium text-ink">{{ row.name }}</td>
                                    <td
                                        v-for="(c, i) in row.months"
                                        :key="i"
                                        class="px-3 py-1.5 text-right"
                                        :class="valueClass(c)"
                                    >
                                        {{ cell(c) }}
                                    </td>
                                    <td class="px-3 py-1.5 text-right font-semibold" :class="valueClass(row.total)">{{ cell(row.total) }}</td>
                                </tr>

                                <!-- Every sub-category, always shown, with gaps flagged -->
                                <tr v-for="b in row.breakdown" :key="row.id + '-' + b.id" class="bg-ink/[0.02]">
                                    <td class="sticky left-0 z-10 bg-surface px-3 py-1 text-ink/60">
                                        <span class="block pl-[22px]">{{ b.name }}</span>
                                    </td>
                                    <td
                                        v-for="(c, i) in b.months"
                                        :key="i"
                                        class="px-3 py-1 text-right"
                                        :class="isMissing(c, i) ? missingClass : valueClass(c)"
                                    >
                                        {{ cell(c) }}
                                    </td>
                                    <td class="px-3 py-1 text-right" :class="valueClass(b.total)">{{ cell(b.total) }}</td>
                                </tr>
                            </template>

                            <!-- Contributions attributed to a child NOT enrolled this year — flagged. -->
                            <template v-if="inactiveRows.length">
                                <tr>
                                    <td :colspan="monthLabels.length + 2" class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-500">
                                        {{ $t('accounting.contributions.inactive_section') }}
                                    </td>
                                </tr>
                                <template v-for="row in inactiveRows" :key="'x' + row.id">
                                    <tr class="hover:bg-ink/5">
                                        <td class="sticky left-0 z-10 bg-surface px-3 py-1.5 font-medium text-amber-700 dark:text-amber-500">{{ row.name }}</td>
                                        <td v-for="(c, i) in row.months" :key="i" class="px-3 py-1.5 text-right" :class="c === 0 ? 'text-ink/25' : 'text-amber-700 dark:text-amber-500'">{{ cell(c) }}</td>
                                        <td class="px-3 py-1.5 text-right font-semibold text-amber-700 dark:text-amber-500">{{ cell(row.total) }}</td>
                                    </tr>
                                    <tr v-for="b in row.breakdown" :key="'x' + row.id + '-' + b.id" class="bg-ink/[0.02]">
                                        <td class="sticky left-0 z-10 bg-surface px-3 py-1 text-ink/50"><span class="block pl-[22px]">{{ b.name }}</span></td>
                                        <td v-for="(c, i) in b.months" :key="i" class="px-3 py-1 text-right" :class="c === 0 ? 'text-ink/25' : 'text-amber-700/80 dark:text-amber-500/80'">{{ cell(c) }}</td>
                                        <td class="px-3 py-1 text-right text-amber-700/80 dark:text-amber-500/80">{{ cell(b.total) }}</td>
                                    </tr>
                                </template>
                            </template>

                            <tr class="border-t-2 border-ink/20 bg-canvas font-semibold">
                                <td class="sticky left-0 z-10 bg-canvas px-3 py-2 text-ink">{{ $t('accounting.contributions.sum') }}</td>
                                <td v-for="(c, i) in monthTotals" :key="i" class="px-3 py-2 text-right" :class="valueClass(c)">
                                    {{ cell(c) }}
                                </td>
                                <td class="px-3 py-2 text-right" :class="valueClass(grandTotal)">{{ cell(grandTotal) }}</td>
                            </tr>

                            <!-- Contributions not linked to a real child — flagged, links to the fix list. -->
                            <tr v-if="unassignedTotal !== 0" class="bg-amber-500/10">
                                <td class="sticky left-0 z-10 bg-amber-50 px-3 py-2 dark:bg-amber-500/10">
                                    <Link :href="unassignedLink" class="inline-flex items-center gap-1.5 font-medium text-amber-700 hover:underline dark:text-amber-500" data-testid="contributions-unassigned">
                                        <ExclamationTriangleIcon class="h-4 w-4 shrink-0" />
                                        {{ $t('accounting.contributions.unassigned') }}
                                    </Link>
                                </td>
                                <td v-for="(c, i) in unassignedMonths" :key="i" class="px-3 py-2 text-right" :class="c === 0 ? 'text-ink/25' : 'text-amber-700 dark:text-amber-500'">
                                    {{ cell(c) }}
                                </td>
                                <td class="px-3 py-2 text-right font-semibold text-amber-700 dark:text-amber-500">{{ cell(unassignedTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
