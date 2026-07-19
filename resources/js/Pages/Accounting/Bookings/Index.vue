<script setup>
import { reactive, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatEuro } from '@/money';
import { t } from '@/i18n';
import {
    index as bookingsIndex,
    create as bookingsCreate,
    edit as bookingsEdit,
    destroy as bookingsDestroy,
} from '@/routes/accounting/bookings';
import { create as transfersCreate } from '@/routes/accounting/transfers';
import { PencilSquareIcon, TrashIcon, PlusIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    bookings: { type: Object, required: true }, // paginator
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
});

const filters = reactive({
    account: props.filters.account ?? '',
    category: props.filters.category ?? '',
    kind: props.filters.kind ?? '',
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    search: props.filters.search ?? '',
});

const statusLabel = computed(() => Object.fromEntries(props.filterOptions.statuses.map((s) => [s.value, s.label])));

let searchTimer = null;
function apply() {
    const query = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null));
    router.get(bookingsIndex().url, query, { preserveState: true, preserveScroll: true, replace: true });
}

watch(() => ({ ...filters, search: undefined }), apply, { deep: true });
watch(
    () => filters.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(apply, 300);
    },
);

function reset() {
    Object.keys(filters).forEach((k) => (filters[k] = ''));
}

function destroy(booking) {
    if (confirm(t('accounting.bookings.delete_confirm'))) {
        router.delete(bookingsDestroy(booking.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="$t('accounting.bookings.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
                    <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.bookings.title') }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="transfersCreate().url"
                        class="flex items-center gap-1 rounded-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/10"
                    >
                        <ArrowsRightLeftIcon class="h-4 w-4" /> {{ $t('accounting.transfers.new') }}
                    </Link>
                    <Link :href="bookingsCreate().url">
                        <PrimaryButton>
                            <PlusIcon class="mr-1 h-4 w-4" /> {{ $t('accounting.bookings.new') }}
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <!-- Filters -->
            <div class="grid grid-cols-2 gap-2 rounded-2xl bg-surface p-4 shadow-sm sm:grid-cols-3 lg:grid-cols-7">
                <select v-model="filters.account" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal">
                    <option value="">{{ $t('accounting.bookings.all_accounts') }}</option>
                    <option v-for="a in filterOptions.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <select v-model="filters.category" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal">
                    <option value="">{{ $t('accounting.bookings.all_categories') }}</option>
                    <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.path }}</option>
                </select>
                <select v-model="filters.kind" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal">
                    <option value="">{{ $t('accounting.bookings.all_kinds') }}</option>
                    <option v-for="k in filterOptions.kinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                </select>
                <select v-model="filters.status" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal">
                    <option value="">{{ $t('accounting.bookings.all_statuses') }}</option>
                    <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <input v-model="filters.from" type="date" :aria-label="$t('accounting.bookings.from')" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal" />
                <input v-model="filters.to" type="date" :aria-label="$t('accounting.bookings.to')" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal" />
                <input v-model="filters.search" type="search" :placeholder="$t('accounting.bookings.search')" class="col-span-2 rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal sm:col-span-3 lg:col-span-1" />
            </div>

            <!-- List -->
            <div class="overflow-hidden rounded-2xl bg-surface shadow-sm">
                <p v-if="!bookings.data.length" class="p-6 text-center text-ink/50">
                    {{ $t('accounting.bookings.empty') }}
                    <button type="button" class="ml-2 text-hort-teal-dark hover:underline" @click="reset">
                        {{ $t('accounting.bookings.reset_filters') }}
                    </button>
                </p>

                <table v-else class="w-full text-sm">
                    <thead class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-ink/40">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ $t('accounting.bookings.booking_date') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('accounting.bookings.category') }}</th>
                            <th class="hidden px-3 py-2 font-medium sm:table-cell">{{ $t('accounting.bookings.counterparty') }}</th>
                            <th class="hidden px-3 py-2 font-medium md:table-cell">{{ $t('accounting.bookings.account') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ $t('accounting.bookings.amount') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/5">
                        <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-ink/5">
                            <td class="whitespace-nowrap px-3 py-2 text-ink/70">
                                {{ b.booking_date }}
                                <span
                                    v-if="b.status !== 'confirmed'"
                                    class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700"
                                >
                                    {{ statusLabel[b.status] }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span v-if="b.is_transfer" class="inline-flex items-center gap-1 rounded-full bg-ink/10 px-2 py-0.5 text-xs font-medium text-ink/70">
                                    <ArrowsRightLeftIcon class="h-3 w-3" /> {{ $t('accounting.bookings.transfer') }}
                                </span>
                                <span v-else class="text-ink">{{ b.category ?? '—' }}</span>
                                <span v-if="b.purpose" class="block max-w-xs truncate text-xs text-ink/40">{{ b.purpose }}</span>
                            </td>
                            <td class="hidden px-3 py-2 text-ink/70 sm:table-cell">
                                <span v-if="b.is_transfer" class="text-ink/50">
                                    {{ b.amount_cents < 0 ? '→' : '←' }} {{ b.counter_account }}
                                </span>
                                <span v-else>{{ b.counterparty ?? '—' }}</span>
                            </td>
                            <td class="hidden px-3 py-2 text-ink/70 md:table-cell">{{ b.account }}</td>
                            <td
                                class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums"
                                :class="b.amount_cents < 0 ? 'text-red-600' : 'text-hort-teal-dark'"
                            >
                                {{ formatEuro(b.amount_cents) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <Link v-if="!b.is_transfer" :href="bookingsEdit(b.id).url" class="rounded p-1 text-ink/50 hover:bg-ink/10 hover:text-ink" :aria-label="$t('common.edit')">
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </Link>
                                    <button type="button" class="rounded p-1 text-ink/50 hover:bg-red-50 hover:text-red-600" :aria-label="$t('common.delete')" @click="destroy(b)">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="bookings.data.length" class="flex items-center justify-between text-sm">
                <Link v-if="bookings.prev_page_url" :href="bookings.prev_page_url" preserve-scroll class="text-hort-teal-dark hover:underline">← {{ $t('activity.newer') }}</Link>
                <span v-else />
                <span class="text-ink/40">{{ bookings.from }}–{{ bookings.to }} / {{ bookings.total }}</span>
                <Link v-if="bookings.next_page_url" :href="bookings.next_page_url" preserve-scroll class="text-hort-teal-dark hover:underline">{{ $t('activity.older') }} →</Link>
                <span v-else />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
