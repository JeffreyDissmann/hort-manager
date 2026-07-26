<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookingFields from '@/Pages/Accounting/Bookings/Partials/BookingFields.vue';
import { formatEuro } from '@/money';
import { t } from '@/i18n';
import { ArrowTopRightOnSquareIcon, ForwardIcon, XMarkIcon, PlusIcon, ClipboardDocumentCheckIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import { review as paperlessReview, attach as paperlessAttach, ignore as paperlessIgnore } from '@/routes/accounting/paperless';
import { store as paperlessBookingsStore } from '@/routes/accounting/paperless/bookings';
import { thumb as paperlessThumb } from '@/routes/accounting/paperless/documents';
import { review as bookingsReview, index as bookingsIndex } from '@/routes/accounting/bookings';

const props = defineProps({
    gate: { type: Object, default: null },
    documents: { type: Array, default: () => [] },
    range: { type: Object, default: null },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    children: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    paymentOptions: { type: Array, default: () => [] },
    paperlessEnabled: { type: Boolean, default: false },
    paperlessUrl: { type: String, default: null },
});

const phase = ref('range'); // 'range' | 'wizard'
const range = ref({ from: props.range?.from ?? '', to: props.range?.to ?? '', payment: props.range?.payment ?? '' });

// Working queue: a snapshot of the documents, advanced locally as each is resolved.
const queue = ref([...props.documents]);
const index = ref(0);
const current = computed(() => queue.value[index.value] ?? null);

const thumbUrl = (id) => paperlessThumb(id).url;
const openUrl = (id) => (props.paperlessUrl ? `${props.paperlessUrl}/documents/${id}/` : null);

function applyRange() {
    router.get(paperlessReview({ query: { from: range.value.from, to: range.value.to, payment: range.value.payment } }).url, {}, { preserveState: false, preserveScroll: true });
}

function start() {
    queue.value = [...props.documents];
    index.value = 0;
    phase.value = 'wizard';
}

const advance = () => index.value++;
const skip = advance;

function attach(bookingId) {
    router.post(
        paperlessAttach().url,
        { document_id: current.value.id, document_title: current.value.title, booking_id: bookingId },
        { preserveScroll: true, preserveState: true, onSuccess: advance },
    );
}

function ignore() {
    router.post(paperlessIgnore().url, { document_id: current.value.id }, { preserveScroll: true, preserveState: true, onSuccess: advance });
}

// Create-booking modal, prefilled from the receipt.
const showCreate = ref(false);
const form = useForm({
    account_id: null,
    category_id: null,
    amount: '',
    booking_date: '',
    valuta_date: '',
    purpose: '',
    comment: '',
    counterparty_child_id: null,
    counterparty_user_id: null,
    counterparty_name: '',
    reversal: false,
    paperless_document_id: null,
    paperless_document_title: null,
});

function openCreate() {
    const doc = current.value;
    form.reset();
    form.amount = doc.amount_cents != null ? (Math.abs(doc.amount_cents) / 100).toFixed(2) : '';
    form.booking_date = doc.created ?? '';
    form.valuta_date = doc.created ?? '';
    form.counterparty_name = doc.correspondent ?? '';
    form.purpose = doc.title ?? '';
    form.paperless_document_id = doc.id;
    form.paperless_document_title = doc.title ?? null;
    showCreate.value = true;
}

function submitCreate() {
    form.post(paperlessBookingsStore().url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showCreate.value = false;
            advance();
        },
    });
}
</script>

<template>
    <Head :title="$t('accounting.paperless_review.title')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.paperless_review.title') }}</h2>
        </template>

        <div class="mx-auto max-w-3xl">
            <!-- Gate: finish the review first -->
            <div v-if="gate" class="rounded-2xl bg-amber-500/10 p-6 text-center">
                <ClipboardDocumentCheckIcon class="mx-auto h-8 w-8 text-amber-600" />
                <p class="mt-2 text-sm text-ink/80">{{ $t('accounting.paperless_review.gate', { count: gate.count }) }}</p>
                <Link :href="bookingsReview().url" class="mt-4 inline-flex items-center gap-1 rounded-lg bg-amber-100 px-4 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-200">
                    {{ $t('accounting.paperless_review.gate_action') }}
                </Link>
            </div>

            <div v-else-if="!paperlessEnabled" class="rounded-2xl bg-surface p-6 text-center text-sm text-ink/50 shadow-sm">
                {{ $t('accounting.paperless_review.disabled') }}
            </div>

            <!-- Range step -->
            <div v-else-if="phase === 'range'" class="rounded-2xl bg-surface p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-ink">{{ $t('accounting.paperless_review.range_title') }}</h3>
                <p class="mt-1 text-sm text-ink/60">{{ $t('accounting.paperless_review.intro') }}</p>
                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-medium text-ink/50">{{ $t('accounting.paperless_review.from') }}</span>
                        <input v-model="range.from" type="date" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal" @change="applyRange" />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-medium text-ink/50">{{ $t('accounting.paperless_review.to') }}</span>
                        <input v-model="range.to" type="date" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal" @change="applyRange" />
                    </label>
                    <label v-if="paymentOptions.length" class="text-sm">
                        <span class="mb-1 block text-xs font-medium text-ink/50">{{ $t('accounting.paperless_review.payment') }}</span>
                        <select v-model="range.payment" class="rounded-md border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal" @change="applyRange">
                            <option value="">{{ $t('accounting.paperless_review.all_payments') }}</option>
                            <option v-for="o in paymentOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
                        </select>
                    </label>
                </div>
                <p class="mt-4 text-sm font-medium text-ink/70">{{ $t('accounting.paperless_review.count', { count: documents.length }) }}</p>
                <div class="mt-4">
                    <PrimaryButton :disabled="documents.length === 0" @click="start">{{ $t('accounting.paperless_review.start') }}</PrimaryButton>
                    <span v-if="documents.length === 0" class="ml-3 text-sm text-ink/50">{{ $t('accounting.paperless_review.no_documents') }}</span>
                </div>
            </div>

            <!-- Wizard step -->
            <template v-else>
                <!-- Done -->
                <div v-if="!current" class="rounded-2xl bg-surface p-8 text-center shadow-sm">
                    <CheckCircleIcon class="mx-auto h-10 w-10 text-hort-teal-dark" />
                    <p class="mt-2 text-sm text-ink/80">{{ $t('accounting.paperless_review.done') }}</p>
                    <Link :href="bookingsIndex().url" class="mt-4 inline-block text-sm text-hort-teal-dark hover:underline">{{ $t('accounting.paperless_review.back') }}</Link>
                </div>

                <div v-else>
                    <p class="mb-2 text-sm font-medium text-ink/50">
                        {{ $t('accounting.paperless_review.progress', { current: index + 1, total: queue.length }) }}
                    </p>

                    <div class="space-y-5 rounded-2xl bg-surface p-5 shadow-sm">
                        <!-- Preview + metadata -->
                        <div class="flex flex-col gap-4 sm:flex-row">
                            <a :href="openUrl(current.id) ?? undefined" target="_blank" rel="noopener" class="shrink-0">
                                <img :src="thumbUrl(current.id)" alt="" class="h-96 w-64 rounded-lg border border-ink/10 bg-surface object-cover object-top shadow-sm transition hover:shadow-md" />
                            </a>
                            <div class="min-w-0">
                                <p class="text-base font-semibold text-ink">{{ current.title || `#${current.id}` }}</p>
                                <p v-if="current.correspondent" class="text-sm text-ink/70">{{ current.correspondent }}</p>
                                <p v-if="current.created" class="text-xs text-ink/50">{{ current.created }}</p>
                                <p v-if="current.amount_cents != null" class="mt-1 text-xl font-semibold tabular-nums text-ink">{{ formatEuro(current.amount_cents) }}</p>
                                <span v-if="current.payment" class="mt-2 inline-block rounded-full bg-ink/10 px-2.5 py-0.5 text-xs font-medium text-ink/70">{{ current.payment }}</span>
                                <a v-if="openUrl(current.id)" :href="openUrl(current.id)" target="_blank" rel="noopener" class="mt-2 flex items-center gap-1 text-xs text-hort-teal-dark hover:underline">
                                    <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" /> {{ $t('accounting.paperless.open') }}
                                </a>
                            </div>
                        </div>

                        <!-- Candidates + actions -->
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-ink">{{ $t('accounting.paperless_review.candidates') }}</h3>
                            <ul v-if="current.candidates.length" class="mt-2 divide-y divide-ink/5 overflow-hidden rounded-lg border border-ink/10">
                                <li v-for="c in current.candidates" :key="c.id" class="flex items-center gap-3 p-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm text-ink">{{ c.category ?? '—' }}</p>
                                        <p class="truncate text-xs text-ink/50">{{ c.booking_date }} · {{ c.account }}<template v-if="c.counterparty"> · {{ c.counterparty }}</template></p>
                                    </div>
                                    <span class="shrink-0 text-sm font-semibold tabular-nums" :class="c.amount_cents < 0 ? 'text-red-600' : 'text-hort-teal-dark'">{{ formatEuro(c.amount_cents) }}</span>
                                    <button type="button" class="shrink-0 rounded-md bg-hort-teal/15 px-3 py-1 text-sm font-medium text-hort-teal-dark transition hover:bg-hort-teal/25" @click="attach(c.id)">
                                        {{ $t('accounting.paperless_review.attach') }}
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-ink/50">{{ $t('accounting.paperless_review.no_candidates') }}</p>

                            <button type="button" class="mt-3 inline-flex items-center gap-1 rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-ink dark:text-hort-navy dark:hover:bg-ink/90" @click="openCreate">
                                <PlusIcon class="h-4 w-4" /> {{ $t('accounting.paperless_review.create') }}
                            </button>
                        </div>
                    </div>

                    <!-- Skip / ignore -->
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-ink/60 transition hover:bg-ink/5" @click="skip">
                            <ForwardIcon class="h-4 w-4" /> {{ $t('accounting.paperless_review.skip') }}
                        </button>
                        <button type="button" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50" @click="ignore">
                            <XMarkIcon class="h-4 w-4" /> {{ $t('accounting.paperless_review.ignore') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Create-booking modal -->
        <Modal :show="showCreate" max-width="2xl" @close="showCreate = false">
            <form class="p-6" @submit.prevent="submitCreate">
                <h3 class="mb-4 text-lg font-semibold text-ink">{{ $t('accounting.paperless_review.create_title') }}</h3>
                <BookingFields
                    :form="form"
                    :accounts="accounts"
                    :categories="categories"
                    :children="children"
                    :users="users"
                    :paperless-enabled="paperlessEnabled"
                    :paperless-url="paperlessUrl"
                    show-reversal
                />
                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" class="text-sm text-ink/70 hover:text-ink" @click="showCreate = false">{{ $t('common.cancel') }}</button>
                    <PrimaryButton :disabled="form.processing">{{ $t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
