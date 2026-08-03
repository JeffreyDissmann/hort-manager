<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { store as transfersStore } from '@/routes/accounting/transfers';
import { index as bookingsIndex } from '@/routes/accounting/bookings';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    accounts: { type: Array, required: true },
});

const form = useForm({
    from_account_id: null,
    to_account_id: null,
    amount: '',
    booking_date: new Date().toISOString().slice(0, 10),
    valuta_date: '',
    purpose: '',
    comment: '',
});

function submit() {
    form.post(transfersStore().url);
}
</script>

<template>
    <Head :title="$t('accounting.transfers.new')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.transfers.title') }}</h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="space-y-6 rounded-2xl bg-surface p-6 shadow-sm">
                <p class="text-sm text-ink/60">{{ $t('accounting.transfers.intro') }}</p>

                <div class="grid items-end gap-4 sm:grid-cols-[1fr_auto_1fr]">
                    <div>
                        <InputLabel for="from_account_id" :value="$t('accounting.transfers.from')" />
                        <select
                            id="from_account_id"
                            v-model="form.from_account_id"
                            class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                        >
                            <option :value="null">{{ $t('accounting.transfers.pick_account') }}</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <ArrowRightIcon class="mb-2 hidden h-5 w-5 text-ink/30 sm:block" />
                    <div>
                        <InputLabel for="to_account_id" :value="$t('accounting.transfers.to')" />
                        <select
                            id="to_account_id"
                            v-model="form.to_account_id"
                            class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                        >
                            <option :value="null">{{ $t('accounting.transfers.pick_account') }}</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                </div>
                <InputError :message="form.errors.from_account_id" />
                <InputError :message="form.errors.to_account_id" />

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel :value="$t('accounting.transfers.amount')" />
                        <TextInput v-model="form.amount" type="number" step="0.01" min="0" class="mt-1 block w-full tabular-nums" placeholder="0,00" />
                        <InputError :message="form.errors.amount" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="booking_date" :value="$t('accounting.bookings.booking_date')" />
                        <DatePicker id="booking_date" v-model="form.booking_date" class="mt-1" />
                        <InputError :message="form.errors.booking_date" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="valuta_date" :value="$t('accounting.bookings.valuta_date')" />
                        <DatePicker id="valuta_date" v-model="form.valuta_date" clearable class="mt-1" />
                        <InputError :message="form.errors.valuta_date" class="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel for="purpose" :value="$t('accounting.bookings.purpose')" />
                    <textarea id="purpose" v-model="form.purpose" rows="2" class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"></textarea>
                    <InputError :message="form.errors.purpose" class="mt-2" />
                </div>

                <div class="flex items-center justify-end gap-4">
                    <Link :href="bookingsIndex().url" class="text-sm text-ink/70 hover:text-ink">{{ $t('common.cancel') }}</Link>
                    <PrimaryButton :disabled="form.processing">{{ $t('common.save') }}</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
