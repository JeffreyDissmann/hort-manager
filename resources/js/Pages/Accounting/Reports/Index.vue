<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatEuro } from '@/money';
import { index as reportsIndex } from '@/routes/accounting/reports';

const props = defineProps({
    year: { type: Number, required: true },
    years: { type: Array, required: true },
    monthLabels: { type: Array, required: true },
    incomeRows: { type: Array, required: true },
    expenseRows: { type: Array, required: true },
    incomeMonths: { type: Array, required: true },
    expenseMonths: { type: Array, required: true },
    netMonths: { type: Array, required: true },
    incomeTotal: { type: Number, required: true },
    expenseTotal: { type: Number, required: true },
    netTotal: { type: Number, required: true },
});

const hasData = computed(() => props.incomeRows.length > 0 || props.expenseRows.length > 0);

function changeYear(event) {
    router.get(reportsIndex({ query: { year: event.target.value } }).url, {}, { preserveScroll: true });
}

// Zero cells read as noise in a wide grid — show a muted dash instead.
const cell = (cents) => (cents === 0 ? '—' : formatEuro(cents));
const cellClass = (cents) =>
    cents === 0 ? 'text-ink/25' : cents < 0 ? 'text-red-600' : 'text-hort-teal-dark';
</script>

<template>
    <Head :title="$t('accounting.reports.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
                    <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.reports.title') }}</h2>
                </div>
                <label class="flex items-center gap-2 text-sm text-ink/60">
                    {{ $t('accounting.reports.year') }}
                    <select
                        :value="year"
                        data-testid="report-year"
                        class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal"
                        @change="changeYear"
                    >
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </label>
            </div>
        </template>

        <div class="space-y-4">
            <p class="text-sm text-ink/50">{{ $t('accounting.reports.intro') }}</p>

            <div class="overflow-hidden rounded-2xl bg-surface shadow-sm">
                <p v-if="!hasData" class="p-6 text-center text-ink/50">{{ $t('accounting.reports.empty') }}</p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="border-b border-ink/10 text-xs uppercase tracking-wide text-ink/40">
                            <tr>
                                <th class="sticky left-0 z-10 bg-surface px-3 py-2 text-left font-medium">
                                    {{ $t('accounting.reports.category') }}
                                </th>
                                <th v-for="(label, i) in monthLabels" :key="i" class="px-3 py-2 text-right font-medium">
                                    {{ label }}
                                </th>
                                <th class="px-3 py-2 text-right font-semibold">{{ $t('accounting.reports.total') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-ink/5">
                            <!-- Income — total first, then the category breakdown -->
                            <tr class="border-t-2 border-ink/20 bg-canvas font-semibold">
                                <td class="sticky left-0 z-10 bg-canvas px-3 py-2 text-ink">{{ $t('accounting.reports.income') }}</td>
                                <td v-for="(c, i) in incomeMonths" :key="i" class="px-3 py-2 text-right" :class="cellClass(c)">{{ cell(c) }}</td>
                                <td class="px-3 py-2 text-right" :class="cellClass(incomeTotal)">{{ cell(incomeTotal) }}</td>
                            </tr>
                            <tr v-for="row in incomeRows" :key="'i' + row.id" class="hover:bg-ink/5">
                                <td class="sticky left-0 z-10 bg-surface px-3 py-1.5 text-ink" :class="{ 'font-medium': row.depth === 0 }">
                                    <span :style="{ paddingLeft: row.depth * 14 + 'px' }">{{ row.name }}</span>
                                </td>
                                <td v-for="(c, i) in row.months" :key="i" class="px-3 py-1.5 text-right" :class="cellClass(c)">
                                    {{ cell(c) }}
                                </td>
                                <td class="px-3 py-1.5 text-right font-semibold" :class="cellClass(row.total)">{{ cell(row.total) }}</td>
                            </tr>

                            <!-- Expense — total first, then the category breakdown -->
                            <tr class="border-t-2 border-ink/20 bg-canvas font-semibold">
                                <td class="sticky left-0 z-10 bg-canvas px-3 py-2 text-ink">{{ $t('accounting.reports.expense') }}</td>
                                <td v-for="(c, i) in expenseMonths" :key="i" class="px-3 py-2 text-right" :class="cellClass(c)">{{ cell(c) }}</td>
                                <td class="px-3 py-2 text-right" :class="cellClass(expenseTotal)">{{ cell(expenseTotal) }}</td>
                            </tr>
                            <tr v-for="row in expenseRows" :key="'e' + row.id" class="hover:bg-ink/5">
                                <td class="sticky left-0 z-10 bg-surface px-3 py-1.5 text-ink" :class="{ 'font-medium': row.depth === 0 }">
                                    <span :style="{ paddingLeft: row.depth * 14 + 'px' }">{{ row.name }}</span>
                                </td>
                                <td v-for="(c, i) in row.months" :key="i" class="px-3 py-1.5 text-right" :class="cellClass(c)">
                                    {{ cell(c) }}
                                </td>
                                <td class="px-3 py-1.5 text-right font-semibold" :class="cellClass(row.total)">{{ cell(row.total) }}</td>
                            </tr>

                            <!-- Net -->
                            <tr class="border-t-2 border-ink/20 bg-canvas font-semibold">
                                <td class="sticky left-0 z-10 bg-canvas px-3 py-2 text-ink">{{ $t('accounting.reports.net') }}</td>
                                <td v-for="(c, i) in netMonths" :key="i" class="px-3 py-2 text-right" :class="cellClass(c)">{{ cell(c) }}</td>
                                <td class="px-3 py-2 text-right" :class="cellClass(netTotal)">{{ cell(netTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
