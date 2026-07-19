<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookingFields from './Partials/BookingFields.vue';
import { formatEuro } from '@/money';
import { reviewSave as bookingsReviewSave } from '@/routes/accounting/bookings';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRightIcon, TrashIcon, ForwardIcon, SparklesIcon } from '@heroicons/vue/24/outline';

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
});

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
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.review.title') }}</h2>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">
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

                <!-- AI suggestion hint -->
                <p v-if="booking.ai_suggested" class="mt-3 flex items-center gap-1 text-xs font-medium text-hort-teal-dark">
                    <SparklesIcon class="h-4 w-4" /> {{ $t('accounting.review.ai_hint') }}
                </p>

                <!-- Full editable form (same fields as the booking editor) -->
                <div class="pt-4">
                    <BookingFields
                        :form="form"
                        :accounts="accounts"
                        :categories="categories"
                        :children="children"
                        :users="users"
                        :direction="booking.direction"
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
                    <PrimaryButton :disabled="form.processing || !form.category_id" @click="send('confirm')">
                        {{ $t('accounting.review.confirm_next') }} <ArrowRightIcon class="ml-1 h-4 w-4" />
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
