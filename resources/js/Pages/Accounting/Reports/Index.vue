<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ChevronRightIcon, DocumentTextIcon, TableCellsIcon } from '@heroicons/vue/24/outline';
import { formatEuro } from '@/money';
import { index as reportsIndex, download as reportsExport } from '@/routes/accounting/reports';
import { index as bookingsIndex } from '@/routes/accounting/bookings';

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

// --- Collapsible parent categories ----------------------------------------
// A top-level category is collapsible when it has (non-zero) child rows.
const hasChildren = (rows, row) => row.depth === 0 && rows.some((r) => r.parent_id === row.id);

// Every collapsible parent across both blocks — the report starts fully collapsed
// (subtotals only), and resets to that whenever the year's rows change.
function collapsibleParents() {
    const all = [...props.incomeRows, ...props.expenseRows];
    return all.filter((r) => hasChildren(all, r)).map((r) => r.id);
}
const collapsed = ref(new Set(collapsibleParents()));
watch(() => [props.incomeRows, props.expenseRows], () => (collapsed.value = new Set(collapsibleParents())));

function toggle(id) {
    const next = new Set(collapsed.value);
    next.has(id) ? next.delete(id) : next.add(id);
    collapsed.value = next;
}
// Hide a child row while its parent is collapsed.
const visible = (rows) => rows.filter((r) => r.depth === 0 || !collapsed.value.has(r.parent_id));
const visibleIncome = computed(() => visible(props.incomeRows));
const visibleExpense = computed(() => visible(props.expenseRows));

// Zero cells read as noise in a wide grid — show a muted dash instead.
const cell = (cents) => (cents === 0 ? '—' : formatEuro(cents));
const cellClass = (cents) =>
    cents === 0 ? 'text-ink/25' : cents < 0 ? 'text-red-600' : 'text-hort-teal-dark';

// Drill down to the bookings that make up a cell's total: same category subtree
// (or income/expense kind for the section totals), confirmed, within that month or
// the whole year. Matches how the report rolls up (confirmed, transfers excluded).
const pad = (n) => String(n).padStart(2, '0');
function drilldown({ category, kind, month }) {
    const range = month
        ? { from: `${props.year}-${pad(month)}-01`, to: `${props.year}-${pad(month)}-${pad(new Date(props.year, month, 0).getDate())}` }
        : { from: `${props.year}-01-01`, to: `${props.year}-12-31` };
    const query = { status: 'confirmed', ...range };
    if (category) query.category = category;
    if (kind) query.kind = kind;
    return bookingsIndex({ query }).url;
}
</script>

<template>
    <Head :title="$t('accounting.reports.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
                    <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.reports.title') }}</h2>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div v-if="hasData" class="flex items-center gap-1">
                        <a
                            :href="reportsExport({ query: { year, format: 'csv' } }).url"
                            class="flex items-center gap-1 rounded-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/10"
                        >
                            <DocumentTextIcon class="h-4 w-4" /> CSV
                        </a>
                        <a
                            :href="reportsExport({ query: { year, format: 'xlsx' } }).url"
                            class="flex items-center gap-1 rounded-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/10"
                        >
                            <TableCellsIcon class="h-4 w-4" /> Excel
                        </a>
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
                                <td v-for="(c, i) in incomeMonths" :key="i" class="px-3 py-2 text-right" :class="cellClass(c)">
                                    <Link v-if="c !== 0" :href="drilldown({ kind: 'income', month: i + 1 })" class="hover:underline">{{ cell(c) }}</Link>
                                    <template v-else>{{ cell(c) }}</template>
                                </td>
                                <td class="px-3 py-2 text-right" :class="cellClass(incomeTotal)">
                                    <Link v-if="incomeTotal !== 0" :href="drilldown({ kind: 'income' })" class="hover:underline">{{ cell(incomeTotal) }}</Link>
                                    <template v-else>{{ cell(incomeTotal) }}</template>
                                </td>
                            </tr>
                            <tr v-for="row in visibleIncome" :key="'i' + row.id" class="hover:bg-ink/5">
                                <td class="sticky left-0 z-10 bg-surface px-3 py-1.5 text-ink">
                                    <div class="flex items-center gap-1" :style="{ paddingLeft: row.depth * 16 + 'px' }">
                                        <button
                                            v-if="hasChildren(incomeRows, row)"
                                            type="button"
                                            class="text-ink/40 transition hover:text-ink"
                                            :aria-expanded="!collapsed.has(row.id)"
                                            @click="toggle(row.id)"
                                        >
                                            <ChevronRightIcon class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-90': !collapsed.has(row.id) }" />
                                        </button>
                                        <span v-else class="inline-block w-3.5" />
                                        <span :class="{ 'font-medium': row.depth === 0 }">{{ row.name }}</span>
                                    </div>
                                </td>
                                <td v-for="(c, i) in row.months" :key="i" class="px-3 py-1.5 text-right" :class="cellClass(c)">
                                    <Link v-if="c !== 0" :href="drilldown({ category: row.id, month: i + 1 })" class="hover:underline">{{ cell(c) }}</Link>
                                    <template v-else>{{ cell(c) }}</template>
                                </td>
                                <td class="px-3 py-1.5 text-right font-semibold" :class="cellClass(row.total)">
                                    <Link v-if="row.total !== 0" :href="drilldown({ category: row.id })" class="hover:underline">{{ cell(row.total) }}</Link>
                                    <template v-else>{{ cell(row.total) }}</template>
                                </td>
                            </tr>

                            <!-- Expense — total first, then the category breakdown -->
                            <tr class="border-t-2 border-ink/20 bg-canvas font-semibold">
                                <td class="sticky left-0 z-10 bg-canvas px-3 py-2 text-ink">{{ $t('accounting.reports.expense') }}</td>
                                <td v-for="(c, i) in expenseMonths" :key="i" class="px-3 py-2 text-right" :class="cellClass(c)">
                                    <Link v-if="c !== 0" :href="drilldown({ kind: 'expense', month: i + 1 })" class="hover:underline">{{ cell(c) }}</Link>
                                    <template v-else>{{ cell(c) }}</template>
                                </td>
                                <td class="px-3 py-2 text-right" :class="cellClass(expenseTotal)">
                                    <Link v-if="expenseTotal !== 0" :href="drilldown({ kind: 'expense' })" class="hover:underline">{{ cell(expenseTotal) }}</Link>
                                    <template v-else>{{ cell(expenseTotal) }}</template>
                                </td>
                            </tr>
                            <tr v-for="row in visibleExpense" :key="'e' + row.id" class="hover:bg-ink/5">
                                <td class="sticky left-0 z-10 bg-surface px-3 py-1.5 text-ink">
                                    <div class="flex items-center gap-1" :style="{ paddingLeft: row.depth * 16 + 'px' }">
                                        <button
                                            v-if="hasChildren(expenseRows, row)"
                                            type="button"
                                            class="text-ink/40 transition hover:text-ink"
                                            :aria-expanded="!collapsed.has(row.id)"
                                            @click="toggle(row.id)"
                                        >
                                            <ChevronRightIcon class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-90': !collapsed.has(row.id) }" />
                                        </button>
                                        <span v-else class="inline-block w-3.5" />
                                        <span :class="{ 'font-medium': row.depth === 0 }">{{ row.name }}</span>
                                    </div>
                                </td>
                                <td v-for="(c, i) in row.months" :key="i" class="px-3 py-1.5 text-right" :class="cellClass(c)">
                                    <Link v-if="c !== 0" :href="drilldown({ category: row.id, month: i + 1 })" class="hover:underline">{{ cell(c) }}</Link>
                                    <template v-else>{{ cell(c) }}</template>
                                </td>
                                <td class="px-3 py-1.5 text-right font-semibold" :class="cellClass(row.total)">
                                    <Link v-if="row.total !== 0" :href="drilldown({ category: row.id })" class="hover:underline">{{ cell(row.total) }}</Link>
                                    <template v-else>{{ cell(row.total) }}</template>
                                </td>
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
