<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { formatEuro } from '@/money';
import { t } from '@/i18n';
import { useAccountingAccess } from '@/accountingAccess';
import {
    index as bookingsIndex,
    create as bookingsCreate,
    edit as bookingsEdit,
    destroy as bookingsDestroy,
    review as bookingsReview,
    reanalyse as bookingsReanalyse,
    relinkReceipts as bookingsRelinkReceipts,
    bulkConfirm as bookingsBulkConfirm,
    download as bookingsExport,
} from '@/routes/accounting/bookings';
import { review as paperlessReview } from '@/routes/accounting/paperless';
import { create as transfersCreate } from '@/routes/accounting/transfers';
import { create as importCreate } from '@/routes/accounting/import';
import { thumb as paperlessThumb } from '@/routes/accounting/paperless/documents';
import { PencilSquareIcon, TrashIcon, PlusIcon, ArrowsRightLeftIcon, ArrowUpTrayIcon, DocumentTextIcon, TableCellsIcon, ClipboardDocumentCheckIcon, SparklesIcon, CheckIcon, ArrowPathIcon, ChevronDownIcon, PaperClipIcon, MagnifyingGlassIcon, BuildingLibraryIcon, TagIcon, ArrowsUpDownIcon, CheckCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    bookings: { type: Object, required: true }, // paginator
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    reviewCount: { type: Number, default: 0 },
    unconfirmedCount: { type: Number, default: 0 },
    confirmableTotal: { type: Number, default: 0 },
    pendingCount: { type: Number, default: 0 },
    aiEnabled: { type: Boolean, default: false },
    paperlessEnabled: { type: Boolean, default: false },
    paperlessUrl: { type: String, default: null },
});

// Deep link to a booking's linked receipt in Paperless (null when unavailable).
const receiptUrl = (b) => (props.paperlessUrl && b.paperless_document_id ? `${props.paperlessUrl}/documents/${b.paperless_document_id}/` : null);

// Hover preview of a linked receipt — teleported to <body> so the table's overflow
// doesn't clip it, positioned just left of the hovered paperclip.
const preview = ref({ id: null, top: 0, left: 0 });
const receiptThumb = (id) => paperlessThumb(id).url;

function showPreview(event, id) {
    const rect = event.currentTarget.getBoundingClientRect();
    const width = 160;
    const height = 224;
    preview.value = {
        id,
        left: Math.max(8, rect.left - width - 8),
        top: Math.min(Math.max(8, rect.top + rect.height / 2 - height / 2), window.innerHeight - height - 8),
    };
}

function hidePreview() {
    preview.value = { ...preview.value, id: null };
}

// Read-only accounting users see the list but none of the write controls.
const { canWrite } = useAccountingAccess();

// While the AI is still analysing imported drafts and the current filter shows
// nothing yet, poll so the freshly-suggested rows appear on their own. Stops as
// soon as rows show up or the draft queue drains.
const shouldPoll = computed(() => props.aiEnabled && props.pendingCount > 0 && props.bookings.data.length === 0);
const { start: startPoll, stop: stopPoll } = usePoll(
    4000,
    { only: ['bookings', 'pendingCount', 'reviewCount', 'unconfirmedCount', 'confirmableTotal'], preserveScroll: true },
    { autoStart: false },
);
watch(shouldPoll, (on) => (on ? startPoll() : stopPoll()), { immediate: true });

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

function relinkReceipts() {
    router.post(bookingsRelinkReceipts().url, {}, { preserveScroll: true });
}

const filters = reactive({
    account: props.filters.account ?? '',
    category: props.filters.category ?? '',
    kind: props.filters.kind ?? '',
    status: props.filters.status ?? '',
    paperless: props.filters.paperless ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    search: props.filters.search ?? '',
});

const statusLabel = computed(() => Object.fromEntries(props.filterOptions.statuses.map((s) => [s.value, s.label])));

const confidenceDot = { 0: 'bg-red-500', 1: 'bg-amber-500', 2: 'bg-hort-teal-dark' };

let searchTimer = null;
const activeFilters = () => Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null));
const hasActiveFilters = computed(() => Object.keys(activeFilters()).length > 0);

function apply() {
    router.get(bookingsIndex().url, activeFilters(), { preserveState: true, preserveScroll: true, replace: true });
}

// Export URL for the current filter (all matching rows, not just the page).
const exportUrl = (format) => bookingsExport({ query: { ...activeFilters(), format } }).url;

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
                    <!-- Export: Excel is the standard; CSV lives under the „more" chevron. -->
                    <div class="inline-flex items-center">
                        <a
                            :href="exportUrl('xlsx')"
                            class="flex items-center gap-1 rounded-l-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/10"
                            data-testid="bookings-export-xlsx"
                        >
                            <TableCellsIcon class="h-4 w-4" /> {{ $t('accounting.bookings.export_excel') }}
                        </a>
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex items-center rounded-r-lg border-l border-ink/10 bg-ink/5 px-2 py-2.5 text-ink transition hover:bg-ink/10"
                                    data-testid="bookings-export-more"
                                >
                                    <ChevronDownIcon class="h-4 w-4" />
                                </button>
                            </template>
                            <template #content>
                                <a
                                    :href="exportUrl('csv')"
                                    class="flex items-center gap-2 px-4 py-2 text-sm text-ink/80 transition hover:bg-ink/5"
                                    data-testid="bookings-export-csv"
                                >
                                    <DocumentTextIcon class="h-4 w-4" /> {{ $t('accounting.bookings.export_csv') }}
                                </a>
                            </template>
                        </Dropdown>
                    </div>
                    <template v-if="canWrite">
                        <!-- Review drafts (primary) with the re-run actions under the chevron -->
                        <div v-if="reviewCount > 0" class="inline-flex items-center">
                            <Link
                                :href="bookingsReview().url"
                                class="flex items-center gap-1 rounded-l-lg bg-amber-100 px-3 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-200"
                                data-testid="bookings-review"
                            >
                                <ClipboardDocumentCheckIcon class="h-4 w-4" />
                                {{ $t('accounting.bookings.review_button') }} ({{ reviewCount }})
                            </Link>
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="flex items-center rounded-r-lg border-l border-amber-200 bg-amber-100 px-2 py-2 text-amber-800 transition hover:bg-amber-200"
                                        data-testid="bookings-review-more"
                                    >
                                        <ChevronDownIcon class="h-4 w-4" />
                                    </button>
                                </template>
                                <template #content>
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-ink transition hover:bg-ink/5"
                                        data-testid="bookings-reanalyse"
                                        @click="reanalyse"
                                    >
                                        <SparklesIcon class="h-4 w-4" /> {{ $t('accounting.bookings.reanalyse') }}
                                    </button>
                                    <button
                                        v-if="paperlessEnabled"
                                        type="button"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-ink transition hover:bg-ink/5"
                                        data-testid="bookings-relink-receipts"
                                        @click="relinkReceipts"
                                    >
                                        <PaperClipIcon class="h-4 w-4" /> {{ $t('accounting.bookings.relink_receipts') }}
                                    </button>
                                </template>
                            </Dropdown>
                        </div>
                        <!-- Nothing suggested yet, but drafts exist → keep the re-run actions reachable -->
                        <template v-else-if="unconfirmedCount > 0">
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/10"
                                data-testid="bookings-reanalyse"
                                @click="reanalyse"
                            >
                                <SparklesIcon class="h-4 w-4" /> {{ $t('accounting.bookings.reanalyse') }}
                            </button>
                            <button
                                v-if="paperlessEnabled"
                                type="button"
                                class="flex items-center gap-1 rounded-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/10"
                                data-testid="bookings-relink-receipts"
                                @click="relinkReceipts"
                            >
                                <PaperClipIcon class="h-4 w-4" /> {{ $t('accounting.bookings.relink_receipts') }}
                            </button>
                        </template>
                        <!-- Import (most-used) is the primary action; „Neue Buchung" and
                             „Neue Umbuchung" live under the attached „more" chevron. -->
                        <div class="inline-flex items-center">
                            <Link
                                :href="importCreate().url"
                                class="flex items-center gap-1.5 rounded-l-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 dark:bg-ink dark:text-hort-navy dark:hover:bg-ink/90"
                                data-testid="bookings-import"
                            >
                                <ArrowUpTrayIcon class="h-4 w-4" /> {{ $t('nav.import') }}
                            </Link>
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="flex items-center rounded-r-md border-l border-white/20 bg-gray-800 px-2 py-2 text-white transition hover:bg-gray-700 dark:border-hort-navy/20 dark:bg-ink dark:text-hort-navy dark:hover:bg-ink/90"
                                        data-testid="bookings-more"
                                    >
                                        <ChevronDownIcon class="h-4 w-4" />
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="bookingsCreate().url" data-testid="bookings-new">
                                        <span class="flex items-center gap-2"><PlusIcon class="h-4 w-4" /> {{ $t('accounting.bookings.new') }}</span>
                                    </DropdownLink>
                                    <DropdownLink :href="transfersCreate().url" data-testid="bookings-transfer">
                                        <span class="flex items-center gap-2"><ArrowsRightLeftIcon class="h-4 w-4" /> {{ $t('accounting.transfers.new') }}</span>
                                    </DropdownLink>
                                    <DropdownLink v-if="paperlessEnabled" :href="paperlessReview().url" data-testid="bookings-assign-receipts">
                                        <span class="flex items-center gap-2"><PaperClipIcon class="h-4 w-4" /> {{ $t('accounting.paperless_review.nav') }}</span>
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <!-- Filters: search leads (with date range); filters read as an iconed toolbar below -->
            <div class="space-y-2 rounded-2xl bg-surface p-4 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="relative w-full sm:flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                        <input v-model="filters.search" type="search" :placeholder="$t('accounting.bookings.search')" class="w-full rounded-md border-ink/20 pl-9 text-sm focus:border-hort-teal focus:ring-hort-teal" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="filters.from" type="date" :aria-label="$t('accounting.bookings.from')" class="w-full flex-1 rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal sm:w-40 sm:flex-none" />
                        <span class="shrink-0 text-ink/40">–</span>
                        <input v-model="filters.to" type="date" :aria-label="$t('accounting.bookings.to')" class="w-full flex-1 rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal sm:w-40 sm:flex-none" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" :class="paperlessEnabled ? 'lg:grid-cols-5' : 'lg:grid-cols-4'">
                    <div class="relative">
                        <BuildingLibraryIcon class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                        <select v-model="filters.account" class="w-full rounded-md border-ink/20 pl-8 text-sm focus:border-hort-teal focus:ring-hort-teal">
                            <option value="">{{ $t('accounting.bookings.all_accounts') }}</option>
                            <option v-for="a in filterOptions.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div class="relative">
                        <TagIcon class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                        <select v-model="filters.category" class="w-full rounded-md border-ink/20 pl-8 text-sm focus:border-hort-teal focus:ring-hort-teal">
                            <option value="">{{ $t('accounting.bookings.all_categories') }}</option>
                            <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.path }}</option>
                        </select>
                    </div>
                    <div class="relative">
                        <ArrowsUpDownIcon class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                        <select v-model="filters.kind" class="w-full rounded-md border-ink/20 pl-8 text-sm focus:border-hort-teal focus:ring-hort-teal">
                            <option value="">{{ $t('accounting.bookings.all_kinds') }}</option>
                            <option v-for="k in filterOptions.kinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                        </select>
                    </div>
                    <div class="relative">
                        <CheckCircleIcon class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                        <select v-model="filters.status" class="w-full rounded-md border-ink/20 pl-8 text-sm focus:border-hort-teal focus:ring-hort-teal">
                            <option value="">{{ $t('accounting.bookings.all_statuses') }}</option>
                            <option v-for="s in filterOptions.statusFilter" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div v-if="paperlessEnabled" class="relative">
                        <PaperClipIcon class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                        <select v-model="filters.paperless" class="w-full rounded-md border-ink/20 pl-8 text-sm focus:border-hort-teal focus:ring-hort-teal">
                            <option value="">{{ $t('accounting.bookings.all_receipts') }}</option>
                            <option value="linked">{{ $t('accounting.bookings.with_receipt') }}</option>
                            <option value="unlinked">{{ $t('accounting.bookings.without_receipt') }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="hasActiveFilters" class="flex justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-medium text-ink/60 transition hover:bg-ink/5 hover:text-ink"
                        data-testid="bookings-reset-filters"
                        @click="reset"
                    >
                        <XMarkIcon class="h-4 w-4" /> {{ $t('accounting.bookings.reset_filters') }}
                    </button>
                </div>
            </div>

            <!-- Bulk-confirm bar -->
            <div v-if="canWrite && hasSelection" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-hort-teal/10 p-3">
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
                    <span v-if="shouldPoll" class="inline-flex items-center gap-2" data-testid="bookings-analysing">
                        <ArrowPathIcon class="h-4 w-4 animate-spin" />
                        {{ $t('accounting.bookings.analysing') }}
                    </span>
                    <template v-else>
                        {{ $t('accounting.bookings.empty') }}
                        <button type="button" class="ml-2 text-hort-teal-dark hover:underline" @click="reset">
                            {{ $t('accounting.bookings.reset_filters') }}
                        </button>
                    </template>
                </p>

                <table v-else class="w-full text-sm">
                    <thead class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-ink/40">
                        <tr>
                            <th v-if="canWrite" class="w-8 px-3 py-2">
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
                            <td v-if="canWrite" class="px-3 py-2">
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
                                    <a
                                        v-if="receiptUrl(b)"
                                        :href="receiptUrl(b)"
                                        target="_blank"
                                        rel="noopener"
                                        class="rounded p-1 text-hort-teal-dark hover:bg-ink/10"
                                        :title="$t('accounting.paperless.has_document')"
                                        :aria-label="$t('accounting.paperless.open')"
                                        data-testid="booking-receipt-link"
                                        @mouseenter="showPreview($event, b.paperless_document_id)"
                                        @mouseleave="hidePreview"
                                        @focus="showPreview($event, b.paperless_document_id)"
                                        @blur="hidePreview"
                                    >
                                        <PaperClipIcon class="h-4 w-4" />
                                    </a>
                                    <template v-if="canWrite">
                                        <Link v-if="!b.is_transfer" :href="bookingsEdit(b.id).url" class="rounded p-1 text-ink/50 hover:bg-ink/10 hover:text-ink" :aria-label="$t('common.edit')">
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </Link>
                                        <button type="button" class="rounded p-1 text-ink/50 hover:bg-red-50 hover:text-red-600" :aria-label="$t('common.delete')" @click="destroy(b)">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <Pagination :paginator="bookings" />
        </div>

        <!-- Hover preview of a linked receipt (teleported so the table's overflow can't clip it) -->
        <Teleport to="body">
            <div
                v-if="preview.id"
                class="pointer-events-none fixed z-50"
                :style="{ top: `${preview.top}px`, left: `${preview.left}px` }"
            >
                <img
                    :src="receiptThumb(preview.id)"
                    alt=""
                    class="h-56 w-40 rounded-lg border border-ink/10 bg-surface object-cover object-top shadow-xl ring-1 ring-black/5"
                />
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
