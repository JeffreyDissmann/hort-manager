<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookingFields from './Partials/BookingFields.vue';
import { update as bookingsUpdate, index as bookingsIndex } from '@/routes/accounting/bookings';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    booking: { type: Object, required: true },
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    users: { type: Array, required: true },
});

const form = useForm({
    account_id: props.booking.account_id,
    category_id: props.booking.category_id,
    amount: props.booking.amount,
    booking_date: props.booking.booking_date,
    valuta_date: props.booking.valuta_date ?? '',
    purpose: props.booking.purpose ?? '',
    comment: props.booking.comment ?? '',
    counterparty_user_id: props.booking.counterparty_user_id,
    counterparty_name: props.booking.counterparty_name ?? '',
});

function submit() {
    form.put(bookingsUpdate(props.booking.id).url);
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
                <BookingFields :form="form" :accounts="accounts" :categories="categories" :users="users" />

                <div class="mt-6 flex items-center justify-end gap-4">
                    <Link :href="bookingsIndex().url" class="text-sm text-ink/70 hover:text-ink">
                        {{ $t('common.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing">{{ $t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
