<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import BookingFields from './Partials/BookingFields.vue';
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

// „Als Umbuchung verbuchen": the counter-leg goes on another account (not this one).
const showTransfer = ref(false);
const otherAccounts = computed(() => props.accounts.filter((a) => a.id !== props.booking.account_id));

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

                <!-- Full editable form (same fields as the booking editor) -->
                <div class="pt-4">
                    <!-- No direction filter: picking an opposite-direction category (e.g.
                         an expense category for a positive line) records a refund/reversal;
                         the bank sign is kept and the sign is derived server-side. -->
                    <BookingFields
                        :form="form"
                        :accounts="accounts"
                        :categories="categories"
                        :children="children"
                        :users="users"
                    />
                </div>

                <!-- Reclassify as an internal transfer (e.g. cash withdrawal → Bar-Kasse) -->
                <div v-if="otherAccounts.length" class="mt-4 rounded-xl bg-ink/[0.03] p-4">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 text-sm font-medium text-ink/70 transition hover:text-ink"
                        data-testid="review-as-transfer"
                        @click="showTransfer = !showTransfer"
                    >
                        <ArrowsRightLeftIcon class="h-4 w-4" /> {{ $t('accounting.review.as_transfer') }}
                    </button>
                    <div v-if="showTransfer" class="mt-3 space-y-3">
                        <p class="text-xs text-ink/50">{{ $t('accounting.review.as_transfer_hint') }}</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-[12rem] flex-1">
                                <InputLabel :value="$t('accounting.review.transfer_to')" />
                                <select
                                    v-model="form.to_account_id"
                                    class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                                >
                                    <option :value="null">{{ $t('accounting.review.transfer_pick') }}</option>
                                    <option v-for="a in otherAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </div>
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded-lg bg-ink/10 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/15 disabled:opacity-50"
                                :disabled="!form.to_account_id || form.processing"
                                data-testid="review-make-transfer"
                                @click="send('transfer')"
                            >
                                <ArrowsRightLeftIcon class="h-4 w-4" /> {{ $t('accounting.review.make_transfer') }}
                            </button>
                        </div>
                        <InputError :message="form.errors.to_account_id" />
                    </div>
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
                    <PrimaryButton :disabled="form.processing || !form.category_id" @click="send('confirm')">
                        {{ $t('accounting.review.confirm_next') }} <ArrowRightIcon class="ml-1 h-4 w-4" />
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
