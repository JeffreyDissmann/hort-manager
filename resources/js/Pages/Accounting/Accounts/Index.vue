<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatEuro } from '@/money';
import { t } from '@/i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    index as accountsIndex,
    create as accountsCreate,
    edit as accountsEdit,
    destroy as accountsDestroy,
} from '@/routes/accounting/accounts';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({
    accounts: { type: Array, required: true },
});

function destroy(account) {
    if (confirm(t('accounting.accounts.delete_confirm', { name: account.name }))) {
        router.delete(accountsDestroy(account.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="$t('accounting.accounts.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">
                        {{ $t('accounting.title') }}
                    </p>
                    <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.accounts.title') }}</h2>
                </div>
                <Link :href="accountsCreate().url">
                    <PrimaryButton>{{ $t('accounting.accounts.new') }}</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl">
            <p class="mb-4 text-sm text-ink/60">{{ $t('accounting.accounts.intro') }}</p>

            <p v-if="!accounts.length" class="rounded-2xl bg-surface p-6 text-center text-ink/50">
                {{ $t('accounting.accounts.empty') }}
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="account in accounts"
                    :key="account.id"
                    class="flex items-center gap-4 rounded-2xl bg-surface p-4 shadow-sm"
                    :class="{ 'opacity-60': !account.active }"
                >
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-medium text-ink">
                            {{ account.name }}
                            <span
                                v-if="!account.active"
                                class="rounded-full bg-ink/10 px-2 py-0.5 text-[11px] font-semibold text-ink/60"
                            >
                                {{ $t('accounting.accounts.active') }}: {{ $t('common.no') }}
                            </span>
                        </p>
                        <p v-if="account.iban" class="truncate text-xs text-ink/50">{{ account.iban }}</p>
                        <p class="text-xs text-ink/50">
                            {{ $t('accounting.accounts.bookings_count') }}: {{ account.bookings_count }}
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-xs text-ink/50">{{ $t('accounting.accounts.balance') }}</p>
                        <p
                            class="font-semibold tabular-nums"
                            :class="account.balance_cents < 0 ? 'text-red-600' : 'text-ink'"
                        >
                            {{ formatEuro(account.balance_cents) }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <Link
                            :href="accountsEdit(account.id).url"
                            class="rounded-lg p-2 text-ink/50 transition hover:bg-ink/5 hover:text-ink"
                            :aria-label="$t('common.edit')"
                        >
                            <PencilSquareIcon class="h-5 w-5" />
                        </Link>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-ink/50 transition hover:bg-red-50 hover:text-red-600"
                            :aria-label="$t('common.delete')"
                            @click="destroy(account)"
                        >
                            <TrashIcon class="h-5 w-5" />
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
