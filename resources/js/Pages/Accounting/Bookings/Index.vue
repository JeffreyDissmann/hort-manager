<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatEuro } from '@/money';
import { t } from '@/i18n';
import {
    index as bookingsIndex,
    create as bookingsCreate,
    edit as bookingsEdit,
    destroy as bookingsDestroy,
    review as bookingsReview,
    reanalyse as bookingsReanalyse,
    bulkConfirm as bookingsBulkConfirm,
} from '@/routes/accounting/bookings';
import { create as transfersCreate } from '@/routes/accounting/transfers';
import { create as importCreate } from '@/routes/accounting/import';
import { PencilSquareIcon, TrashIcon, PlusIcon, ArrowsRightLeftIcon, ArrowDownTrayIcon, ClipboardDocumentCheckIcon, SparklesIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    bookings: { type: Object, required: true }, // paginator
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    reviewCount: { type: Number, default: 0 },
    unconfirmedCount: { type: Number, default: 0 },
    confirmableTotal: { type: Number, default: 0 },
});

// --- Bulk selection / confirm ---------------------------------------------
const selectedIds = ref(new Set());
const selectAllMatching = ref(false);

const confirmableRows = computed(() => props.bookings.data.filter((b) => b.can_confirm));
const allPageSelected = computed(
    () => confirmableRows.value.length > 0 && confirmableRows.value.every((b) => selectedIds.value.has(b.id)),
);
const hasSelection = computed(() => selectAllMatching.value || selectedIds.value.size > 0);
const selectionCount = computed(() => (selectAllMatching.value ? props.confirmableTotal : selectedIds.value.size));
const canSelectAllMatching = computed(
    () => allPageSelected.value && !selectAllMatching.value && props.confirmableTotal > selectedIds.value.size,
);

function toggleRow(booking) {
    selectAllMatching.value = false;
    const next = new Set(selectedIds.value);
    next.has(booking.id) ? next.delete(booking.id) : next.add(booking.id);
    selectedIds.value = next;
}

function togglePage() {
    selectAllMatching.value = false;
    const next = new Set(selectedIds.value);
    const select = !allPageSelected.value;
    confirmableRows.value.forEach((b) => (select ? next.add(b.id) : next.delete(b.id)));
    selectedIds.value = next;
}

function clearSelection() {
    selectedIds.value = new Set();
    selectAllMatching.value = false;
}

function confirmSelected() {
    const activeFilters = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null));
    const payload = selectAllMatching.value
        ? { all: true, filters: activeFilters }
        : { ids: [...selectedIds.value] };
    router.post(bookingsBulkConfirm().url, payload, { preserveScroll: true, onSuccess: clearSelection });
}

// Reset selection whenever the list changes (filter / page navigation).
watch(() => props.bookings, clearSelection);

function reanalyse() {
    if (confirm(t('accounting.bookings.reanalyse_confirm'))) {
        router.post(bookingsReanalyse().url, {}, { preserveScroll: true });
    }
}

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

const confidenceDot = { 0: 'bg-red-500', 1: 'bg-amber-500', 2: 'bg-hort-teal-dark' };

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
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="unconfirmedCount > 0"
                        type="button"
                        class="flex items-center gap-1 rounded-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/10"
                        data-testid="bookings-reanalyse"
                        @click="reanalyse"
                    >
                        <SparklesIcon class="h-4 w-4" /> {{ $t('accounting.bookings.reanalyse') }}
                    </button>
                    <Link
                        v-if="reviewCount > 0"
                        :href="bookingsReview().url"
                        class="flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-200"
                        data-testid="bookings-review"
                    >
                        <ClipboardDocumentCheckIcon class="h-4 w-4" />
                        {{ $t('accounting.bookings.review_button') }} ({{ reviewCount }})
                    </Link>
                    <Link
                        :href="importCreate().url"
                        class="flex items-center gap-1 rounded-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/10"
                        data-testid="bookings-import"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" /> {{ $t('nav.import') }}
                    </Link>
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
                    <option v-for="s in filterOptions.statusFilter" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <input v-model="filters.from" type="date" :aria-label="$t('accounting.bookings.from')" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal" />
                <input v-model="filters.to" type="date" :aria-label="$t('accounting.bookings.to')" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal" />
                <input v-model="filters.search" type="search" :placeholder="$t('accounting.bookings.search')" class="col-span-2 rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal sm:col-span-3 lg:col-span-1" />
            </div>

            <!-- Bulk-confirm bar -->
            <div v-if="hasSelection" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-hort-teal/10 p-3">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span class="font-medium text-ink">
                        {{ selectAllMatching
                            ? $t('accounting.bookings.all_matching_selected', { count: selectionCount })
                            : $t('accounting.bookings.selected_count', { count: selectionCount }) }}
                    </span>
                    <button v-if="canSelectAllMatching" type="button" class="text-hort-teal-dark hover:underline" @click="selectAllMatching = true">
                        {{ $t('accounting.bookings.select_all_matching', { count: confirmableTotal }) }}
                    </button>
                    <button type="button" class="text-ink/50 hover:text-ink" @click="clearSelection">
                        {{ $t('accounting.bookings.clear_selection') }}
                    </button>
                    <span class="text-ink/40">· {{ $t('accounting.bookings.confirm_selected_hint') }}</span>
                </div>
                <PrimaryButton :disabled="selectionCount === 0" @click="confirmSelected">
                    <CheckIcon class="mr-1 h-4 w-4" /> {{ $t('accounting.bookings.confirm_selected') }} ({{ selectionCount }})
                </PrimaryButton>
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
                            <th class="w-8 px-3 py-2">
                                <input
                                    v-if="confirmableRows.length"
                                    type="checkbox"
                                    :checked="allPageSelected"
                                    class="rounded border-ink/20 text-hort-teal-dark focus:ring-hort-teal"
                                    :aria-label="$t('accounting.bookings.confirm_selected')"
                                    @change="togglePage"
                                />
                            </th>
                            <th class="px-3 py-2 font-medium">{{ $t('accounting.bookings.booking_date') }}</th>
                            <th class="px-3 py-2 font-medium">{{ $t('accounting.bookings.category') }}</th>
                            <th class="hidden px-3 py-2 font-medium sm:table-cell">{{ $t('accounting.bookings.counterparty') }}</th>
                            <th class="hidden px-3 py-2 font-medium md:table-cell">{{ $t('accounting.bookings.account') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ $t('accounting.bookings.amount') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/5">
                        <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-ink/5" :class="{ 'bg-hort-teal/5': selectAllMatching ? b.can_confirm : selectedIds.has(b.id) }">
                            <td class="px-3 py-2">
                                <input
                                    v-if="b.can_confirm"
                                    type="checkbox"
                                    :checked="selectAllMatching || selectedIds.has(b.id)"
                                    :disabled="selectAllMatching"
                                    class="rounded border-ink/20 text-hort-teal-dark focus:ring-hort-teal disabled:opacity-50"
                                    @change="toggleRow(b)"
                                />
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-ink/70">
                                {{ b.booking_date }}
                                <span
                                    v-if="b.status !== 'confirmed'"
                                    class="ml-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700"
                                >
                                    <span
                                        v-if="b.confidence != null"
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="confidenceDot[b.confidence]"
                                        :title="$t('accounting.review.confidence') + ': ' + $t(`enums.suggestion_confidence.${b.confidence}`)"
                                    />
                                    {{ statusLabel[b.status] }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span v-if="b.is_transfer" class="inline-flex items-center gap-1 rounded-full bg-ink/10 px-2 py-0.5 text-xs font-medium text-ink/70">
                                    <ArrowsRightLeftIcon class="h-3 w-3" /> {{ $t('accounting.bookings.transfer') }}
                                </span>
                                <span v-else class="text-ink">{{ b.category ?? '—' }}</span>
                                <!-- Full purpose while unconfirmed, so it's easy to check; truncated once confirmed. -->
                                <span
                                    v-if="b.purpose"
                                    class="block text-xs text-ink/40"
                                    :class="b.status === 'confirmed' ? 'max-w-xs truncate' : 'whitespace-pre-wrap break-words'"
                                >
                                    {{ b.purpose }}
                                </span>
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
            <Pagination :paginator="bookings" />
        </div>
    </AuthenticatedLayout>
</template>
