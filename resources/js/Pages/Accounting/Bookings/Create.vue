<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookingFields from './Partials/BookingFields.vue';
import { store as bookingsStore, index as bookingsIndex } from '@/routes/accounting/bookings';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    children: { type: Array, default: () => [] },
    users: { type: Array, required: true },
});

const form = useForm({
    account_id: null,
    category_id: null,
    amount: '',
    booking_date: new Date().toISOString().slice(0, 10),
    valuta_date: '',
    purpose: '',
    comment: '',
    counterparty_child_id: null,
    counterparty_user_id: null,
    counterparty_name: '',
});

function submit() {
    form.post(bookingsStore().url);
}
</script>

<template>
    <Head :title="$t('accounting.bookings.new')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.bookings.new') }}</h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="rounded-2xl bg-surface p-6 shadow-sm">
                <BookingFields :form="form" :accounts="accounts" :categories="categories" :children="children" :users="users" />

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
