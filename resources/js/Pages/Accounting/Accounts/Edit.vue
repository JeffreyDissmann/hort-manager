<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AccountFields from './Partials/AccountFields.vue';
import { update as accountsUpdate, index as accountsIndex } from '@/routes/accounting/accounts';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    account: { type: Object, required: true },
});

const form = useForm({
    name: props.account.name,
    iban: props.account.iban ?? '',
    opening_balance: props.account.opening_balance ?? '',
    opening_balance_date: props.account.opening_balance_date ?? '',
    active: props.account.active,
});

function submit() {
    form.put(accountsUpdate(props.account.id).url);
}
</script>

<template>
    <Head :title="$t('accounting.accounts.edit')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">
                {{ $t('accounting.title') }}
            </p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.accounts.edit') }}</h2>
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
