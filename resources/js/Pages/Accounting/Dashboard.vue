<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ClipboardDocumentCheckIcon, ChartBarIcon, UsersIcon, CheckCircleIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
import { formatEuro } from '@/money';
import { index as bookingsIndex } from '@/routes/accounting/bookings';
import { index as reportsIndex } from '@/routes/accounting/reports';
import { index as contributionsIndex } from '@/routes/accounting/contributions';

const props = defineProps({
    accounts: { type: Array, required: true },
    reviewCount: { type: Number, required: true },
    asOf: { type: String, default: null }, // ISO date of the newest confirmed booking
    periods: { type: Object, required: true }, // { quarter, year } ISO period-end dates
});

const locale = () => usePage().props.locale ?? 'de';

// "Data accurate to" — the newest booking's date, localized.
const asOfLabel = computed(() =>
    props.asOf
        ? new Date(props.asOf).toLocaleDateString(locale(), { year: 'numeric', month: 'long', day: 'numeric' })
        : null,
);
const shortDate = (iso) => new Date(iso).toLocaleDateString(locale(), { day: '2-digit', month: '2-digit', year: 'numeric' });
const balanceClass = (cents) => (cents < 0 ? 'text-red-600' : 'text-hort-teal-dark');
</script>

<template>
    <Head :title="$t('accounting.dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.dashboard.title') }}</h2>
            <p v-if="asOfLabel" class="mt-0.5 text-xs text-ink/40">{{ $t('accounting.dashboard.as_of', { date: asOfLabel }) }}</p>
        </template>

        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Data freshness: newest booking date -->
                <div v-if="asOfLabel" class="flex items-center gap-4 rounded-2xl bg-surface p-4 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-hort-teal/15">
                        <CalendarDaysIcon class="h-6 w-6 text-hort-teal-dark" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-ink/40">{{ $t('accounting.dashboard.as_of_label') }}</p>
                        <p class="text-xl font-semibold text-ink">{{ asOfLabel }}</p>
                    </div>
                </div>

                <!-- Attention: bookings still to review -->
                <Link
                    :href="bookingsIndex().url"
                    class="flex items-center gap-3 rounded-2xl p-4 shadow-sm transition hover:shadow"
                    :class="reviewCount > 0 ? 'bg-amber-500/10' : 'bg-surface'"
                >
                    <component
                        :is="reviewCount > 0 ? ClipboardDocumentCheckIcon : CheckCircleIcon"
                        class="h-8 w-8 shrink-0"
                        :class="reviewCount > 0 ? 'text-amber-600' : 'text-hort-teal-dark'"
                    />
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ $t('accounting.dashboard.review') }}</p>
                        <p class="text-sm text-ink/60">
                            {{ reviewCount > 0 ? $t('accounting.dashboard.review_hint', { count: reviewCount }) : $t('accounting.dashboard.all_clear') }}
                        </p>
                    </div>
                    <span v-if="reviewCount > 0" class="ml-auto text-2xl font-bold tabular-nums text-amber-600">{{ reviewCount }}</span>
                </Link>
            </div>

            <!-- Account balances: now, previous quarter-end, previous year-end -->
            <div class="rounded-2xl bg-surface p-5 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-ink">{{ $t('accounting.dashboard.balances') }}</h3>
                <p v-if="!accounts.length" class="text-sm text-ink/50">{{ $t('accounting.dashboard.no_accounts') }}</p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="text-xs uppercase tracking-wide text-ink/40">
                            <tr>
                                <th class="py-2 text-left font-medium">{{ $t('accounting.dashboard.account') }}</th>
                                <th class="py-2 text-right font-medium">
                                    {{ $t('accounting.dashboard.balance_year') }}
                                    <span class="block font-normal normal-case text-ink/30">{{ shortDate(periods.year) }}</span>
                                </th>
                                <th class="py-2 text-right font-medium">
                                    {{ $t('accounting.dashboard.balance_quarter') }}
                                    <span class="block font-normal normal-case text-ink/30">{{ shortDate(periods.quarter) }}</span>
                                </th>
                                <th class="py-2 text-right font-medium">{{ $t('accounting.dashboard.balance_current') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/5">
                            <tr v-for="a in accounts" :key="a.id">
                                <td class="py-2 text-ink">{{ a.name }}</td>
                                <td class="py-2 text-right text-ink/60">{{ formatEuro(a.balance_year_cents) }}</td>
                                <td class="py-2 text-right text-ink/60">{{ formatEuro(a.balance_quarter_cents) }}</td>
                                <td class="py-2 text-right font-semibold" :class="balanceClass(a.balance_cents)">{{ formatEuro(a.balance_cents) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick links to the reports -->
            <div>
                <h3 class="mb-3 text-sm font-semibold text-ink">{{ $t('accounting.dashboard.links') }}</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Link :href="reportsIndex().url" class="flex items-center gap-3 rounded-2xl bg-surface p-4 shadow-sm transition hover:shadow">
                        <ChartBarIcon class="h-6 w-6 text-hort-teal-dark" />
                        <span class="text-sm font-medium text-ink">{{ $t('nav.reports') }}</span>
                    </Link>
                    <Link :href="contributionsIndex().url" class="flex items-center gap-3 rounded-2xl bg-surface p-4 shadow-sm transition hover:shadow">
                        <UsersIcon class="h-6 w-6 text-hort-teal-dark" />
                        <span class="text-sm font-medium text-ink">{{ $t('nav.contributions') }}</span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
