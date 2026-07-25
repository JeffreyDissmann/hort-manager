<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookingFields from './Partials/BookingFields.vue';
import BookingModeToggle from '@/Components/Accounting/BookingModeToggle.vue';
import TransferForm from '@/Components/Accounting/TransferForm.vue';
import { formatEuro } from '@/money';
import { reviewSave as bookingsReviewSave, index as bookingsIndex } from '@/routes/accounting/bookings';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRightIcon, TrashIcon, ForwardIcon, SparklesIcon, ChevronLeftIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    booking: { type: Object, required: true },
    remaining: { type: Number, required: true },
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    children: { type: Array, default: () => [] },
    users: { type: Array, required: true },
});

const form = useForm({
    action: 'confirm',
    account_id: props.booking.account_id,
    category_id: props.booking.category_id,
    amount: props.booking.amount,
    booking_date: props.booking.booking_date,
    valuta_date: props.booking.valuta_date ?? '',
    purpose: props.booking.purpose ?? '',
    comment: props.booking.comment ?? '',
    counterparty_child_id: props.booking.counterparty_child_id,
    counterparty_user_id: props.booking.counterparty_user_id,
    counterparty_name: props.booking.counterparty_name ?? '',
    to_account_id: null,
});

// Intent: a normal „Buchung" or an „Umbuchung" (transfer to another own account).
const mode = ref('booking');
const toAccountId = ref(null);

function createTransfer() {
    form.to_account_id = toAccountId.value;
    send('transfer');
}

// A picked category whose direction is opposite the bank sign is a refund/reversal;
// the sign stays anchored to the statement (derived server-side on confirm).
const selectedCategory = computed(() => props.categories.find((c) => c.id === form.category_id) ?? null);
const isReversal = computed(() => !!selectedCategory.value && selectedCategory.value.direction !== props.booking.direction);

const confidenceClass = {
    0: 'bg-red-100 text-red-700',
    1: 'bg-amber-100 text-amber-700',
    2: 'bg-hort-teal/15 text-hort-teal-dark',
};

// preserveState:false so the next draft's props fully replace the form.
function send(action) {
    form.transform((data) => ({ ...data, action })).patch(bookingsReviewSave(props.booking.id).url, {
        preserveState: false,
    });
}
</script>

<template>
    <Head :title="$t('accounting.review.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        :href="bookingsIndex().url"
                        class="flex shrink-0 items-center gap-1 rounded-lg bg-ink/5 px-2.5 py-1.5 text-sm font-medium text-ink/70 transition hover:bg-ink/10 hover:text-ink"
                        data-testid="review-back"
                    >
                        <ChevronLeftIcon class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ $t('accounting.import.view_bookings') }}</span>
                    </Link>
                    <h2 class="truncate text-xl font-semibold text-ink">{{ $t('accounting.review.title') }}</h2>
                </div>
                <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">
                    {{ $t('accounting.review.remaining', { count: remaining }) }}
                </span>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <div class="rounded-2xl bg-surface p-6 shadow-sm">
                <!-- Reference: the original statement line -->
                <div class="flex items-start justify-between gap-4 border-b border-ink/10 pb-4">
                    <div class="min-w-0">
                        <p class="text-sm text-ink/50">{{ booking.booking_date }} · {{ booking.account }}</p>
                        <p class="mt-1 whitespace-pre-line break-words text-sm text-ink/70">{{ booking.purpose || '—' }}</p>
                    </div>
                    <p
                        class="shrink-0 text-xl font-semibold tabular-nums"
                        :class="booking.amount_cents < 0 ? 'text-red-600' : 'text-hort-teal-dark'"
                    >
                        {{ formatEuro(booking.amount_cents) }}
                    </p>
                </div>

                <!-- AI suggestion hint + confidence -->
                <div v-if="booking.ai_suggested" class="mt-3 flex items-center gap-2">
                    <span class="flex items-center gap-1 text-xs font-medium text-hort-teal-dark">
                        <SparklesIcon class="h-4 w-4" /> {{ $t('accounting.review.ai_hint') }}
                    </span>
                    <span
                        v-if="booking.confidence != null"
                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="confidenceClass[booking.confidence]"
                    >
                        {{ $t('accounting.review.confidence') }}: {{ $t(`enums.suggestion_confidence.${booking.confidence}`) }}
                    </span>
                </div>

                <!-- Intent: normal booking vs. internal transfer (needs ≥2 accounts) -->
                <div v-if="accounts.length > 1" class="pt-4">
                    <BookingModeToggle v-model="mode" />
                </div>

                <!-- Booking: the full editable form -->
                <div v-if="mode === 'booking'" class="pt-4">
                    <!-- No direction filter: picking an opposite-direction category (e.g.
                         an expense category for a positive line) records a refund/reversal;
                         the bank sign is kept and the sign is derived server-side. -->
                    <BookingFields
                        :form="form"
                        :accounts="accounts"
                        :categories="categories"
                        :children="children"
                        :users="users"
                    >
                        <template #category-note>
                            <p
                                v-if="isReversal"
                                class="mt-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-700 dark:text-amber-500"
                                data-testid="review-reversal-note"
                            >
                                {{ $t('accounting.review.reversal_note') }}
                            </p>
                        </template>
                    </BookingFields>
                </div>

                <!-- Umbuchung: the focused transfer sub-form -->
                <div v-else class="pt-4">
                    <TransferForm
                        v-model:to-account-id="toAccountId"
                        :accounts="accounts"
                        :current-account-id="booking.account_id"
                        :from-account-name="booking.account"
                        :amount-cents="booking.amount_cents"
                        :error="form.errors.to_account_id"
                    />
                </div>

                <!-- Actions -->
                <div class="mt-6 flex items-center justify-between gap-2 border-t border-ink/10 pt-4">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                            @click="send('discard')"
                        >
                            <TrashIcon class="h-4 w-4" /> {{ $t('accounting.review.discard') }}
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-ink/60 transition hover:bg-ink/5"
                            @click="send('skip')"
                        >
                            <ForwardIcon class="h-4 w-4" /> {{ $t('accounting.review.skip') }}
                        </button>
                    </div>
                    <PrimaryButton
                        v-if="mode === 'booking'"
                        :disabled="form.processing || !form.category_id"
                        @click="send('confirm')"
                    >
                        {{ $t('accounting.review.confirm_next') }} <ArrowRightIcon class="ml-1 h-4 w-4" />
                    </PrimaryButton>
                    <PrimaryButton
                        v-else
                        :disabled="form.processing || !toAccountId"
                        data-testid="make-transfer"
                        @click="createTransfer"
                    >
                        <ArrowsRightLeftIcon class="mr-1 h-4 w-4" /> {{ $t('accounting.review.make_transfer') }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
