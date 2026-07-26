<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import BookingFields from './Partials/BookingFields.vue';
import BookingModeToggle from '@/Components/Accounting/BookingModeToggle.vue';
import TransferForm from '@/Components/Accounting/TransferForm.vue';
import { update as bookingsUpdate, index as bookingsIndex, convertTransfer as bookingsConvertTransfer } from '@/routes/accounting/bookings';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { t } from '@/i18n';
import { ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    booking: { type: Object, required: true },
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    children: { type: Array, default: () => [] },
    users: { type: Array, required: true },
    statuses: { type: Array, required: true },
    paperlessEnabled: { type: Boolean, default: false },
    paperlessUrl: { type: String, default: null },
});

const form = useForm({
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
    reversal: props.booking.reversal ?? false,
    paperless_document_id: props.booking.paperless_document_id ?? null,
    paperless_document_title: props.booking.paperless_document_title ?? null,
    status: props.booking.status,
});

function submit() {
    // Saving changes to an already-confirmed ("old") booking asks an extra time.
    if (props.booking.status === 'confirmed' && !confirm(t('accounting.bookings.edit_confirmed_confirm'))) {
        return;
    }
    form.put(bookingsUpdate(props.booking.id).url);
}

// Intent: a normal „Buchung" or an „Umbuchung" (transfer to another own account).
const mode = ref('booking');
const toAccountId = ref(null);
const transferForm = useForm({ to_account_id: null });
const fromAccountName = computed(() => props.accounts.find((a) => a.id === props.booking.account_id)?.name ?? '');

function createTransfer() {
    transferForm.to_account_id = toAccountId.value;
    transferForm.post(bookingsConvertTransfer(props.booking.id).url);
}
</script>

<template>
    <Head :title="$t('accounting.bookings.edit')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.bookings.edit') }}</h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="rounded-2xl bg-surface p-6 shadow-sm">
                <!-- Intent: normal booking vs. internal transfer (needs ≥2 accounts) -->
                <BookingModeToggle v-if="accounts.length > 1" v-model="mode" class="mb-6" />

                <template v-if="mode === 'booking'">
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

                    <div class="mt-6 border-t border-ink/10 pt-4">
                        <InputLabel for="status" :value="$t('accounting.bookings.status')" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full max-w-xs rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                        >
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </template>

                <TransferForm
                    v-else
                    v-model:to-account-id="toAccountId"
                    :accounts="accounts"
                    :current-account-id="booking.account_id"
                    :from-account-name="fromAccountName"
                    :amount-cents="booking.amount_cents"
                    :error="transferForm.errors.to_account_id"
                />

                <div class="mt-6 flex flex-wrap items-center justify-end gap-x-4 gap-y-3">
                    <Link :href="bookingsIndex().url" class="text-sm text-ink/70 hover:text-ink">
                        {{ $t('common.cancel') }}
                    </Link>
                    <PrimaryButton v-if="mode === 'booking'" :disabled="form.processing">{{ $t('common.save') }}</PrimaryButton>
                    <PrimaryButton
                        v-else
                        type="button"
                        class="whitespace-nowrap"
                        :disabled="transferForm.processing || !toAccountId"
                        data-testid="make-transfer"
                        @click="createTransfer"
                    >
                        <ArrowsRightLeftIcon class="mr-1 h-4 w-4" /> {{ $t('accounting.review.make_transfer') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
