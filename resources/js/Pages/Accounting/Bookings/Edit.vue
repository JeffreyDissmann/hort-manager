<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import BookingFields from './Partials/BookingFields.vue';
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
    status: props.booking.status,
});

function submit() {
    // Saving changes to an already-confirmed ("old") booking asks an extra time.
    if (props.booking.status === 'confirmed' && !confirm(t('accounting.bookings.edit_confirmed_confirm'))) {
        return;
    }
    form.put(bookingsUpdate(props.booking.id).url);
}

// „Als Umbuchung verbuchen": reclassify this booking as an internal transfer to
// another account (reuses this line as one leg, creates the matching leg).
const showTransfer = ref(false);
const transferForm = useForm({ to_account_id: null });
const otherAccounts = computed(() => props.accounts.filter((a) => a.id !== props.booking.account_id));

function convertToTransfer() {
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
                <BookingFields :form="form" :accounts="accounts" :categories="categories" :children="children" :users="users" show-reversal />

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

                <div class="mt-6 flex items-center justify-end gap-4">
                    <Link :href="bookingsIndex().url" class="text-sm text-ink/70 hover:text-ink">
                        {{ $t('common.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing">{{ $t('common.save') }}</PrimaryButton>
                </div>
            </form>

            <!-- Reclassify this booking as an internal transfer (e.g. a cash withdrawal → Bar-Kasse) -->
            <div v-if="otherAccounts.length" class="mt-4 rounded-2xl bg-surface p-5 shadow-sm">
                <button
                    type="button"
                    class="flex items-center gap-1.5 text-sm font-medium text-ink/70 transition hover:text-ink"
                    data-testid="edit-as-transfer"
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
                                v-model="transferForm.to_account_id"
                                class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                            >
                                <option :value="null">{{ $t('accounting.review.transfer_pick') }}</option>
                                <option v-for="a in otherAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-lg bg-ink/10 px-3 py-2 text-sm font-medium text-ink transition hover:bg-ink/15 disabled:opacity-50"
                            :disabled="!transferForm.to_account_id || transferForm.processing"
                            data-testid="edit-make-transfer"
                            @click="convertToTransfer"
                        >
                            <ArrowsRightLeftIcon class="h-4 w-4" /> {{ $t('accounting.review.make_transfer') }}
                        </button>
                    </div>
                    <InputError :message="transferForm.errors.to_account_id" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
