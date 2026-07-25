<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { formatEuro } from '@/money';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';

// The „Umbuchung" sub-form, shown when a booking's intent is set to transfer. The
// line itself (account, amount, date) is a fixed fact of the statement, so it's shown
// read-only; the only input is the Gegenkonto. Shared by the review and edit windows.
const props = defineProps({
    accounts: { type: Array, required: true },
    currentAccountId: { type: [Number, String], required: true },
    fromAccountName: { type: String, default: '' },
    amountCents: { type: Number, default: 0 },
    error: { type: String, default: null },
});
const toAccountId = defineModel('toAccountId', { type: [Number, null], default: null });

const otherAccounts = computed(() => props.accounts.filter((a) => a.id !== props.currentAccountId));
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-ink/60">{{ $t('accounting.review.as_transfer_hint') }}</p>

        <!-- Read-only facts of the line being moved -->
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg bg-ink/[0.03] px-3 py-2 text-sm">
            <span class="font-semibold tabular-nums" :class="amountCents < 0 ? 'text-red-600' : 'text-hort-teal-dark'">
                {{ formatEuro(amountCents) }}
            </span>
            <span class="text-ink/50">{{ $t('accounting.review.transfer_from') }}</span>
            <span class="font-medium text-ink">{{ fromAccountName }}</span>
            <ArrowRightIcon class="h-4 w-4 text-ink/30" />
        </div>

        <div>
            <InputLabel :value="$t('accounting.review.transfer_to')" />
            <select
                v-model="toAccountId"
                data-testid="transfer-to"
                class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
            >
                <option :value="null">{{ $t('accounting.review.transfer_pick') }}</option>
                <option v-for="a in otherAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
            <InputError :message="error" class="mt-2" />
        </div>
    </div>
</template>
