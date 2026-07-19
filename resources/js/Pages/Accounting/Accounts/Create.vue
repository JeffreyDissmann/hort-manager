<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AccountFields from './Partials/AccountFields.vue';
import { store as accountsStore, index as accountsIndex } from '@/routes/accounting/accounts';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    iban: '',
    opening_balance: '',
    opening_balance_date: '',
    active: true,
});

function submit() {
    form.post(accountsStore().url);
}
</script>

<template>
    <Head :title="$t('accounting.accounts.new')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">
                {{ $t('accounting.title') }}
            </p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.accounts.new') }}</h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="rounded-2xl bg-surface p-6 shadow-sm">
                <AccountFields :form="form" />

                <div class="mt-6 flex items-center justify-end gap-4">
                    <Link :href="accountsIndex().url" class="text-sm text-ink/70 hover:text-ink">
                        {{ $t('common.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing">{{ $t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
