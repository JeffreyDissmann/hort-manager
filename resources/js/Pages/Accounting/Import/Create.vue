<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { store as importStore } from '@/routes/accounting/import';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowUpTrayIcon } from '@heroicons/vue/24/outline';

defineProps({
    accounts: { type: Array, required: true },
});

const form = useForm({
    account_id: null,
    file: null,
});

function submit() {
    form.post(importStore().url);
}
</script>

<template>
    <Head :title="$t('accounting.import.title')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.import.title') }}</h2>
        </template>

        <div class="mx-auto max-w-xl">
            <form @submit.prevent="submit" class="space-y-6 rounded-2xl bg-surface p-6 shadow-sm">
                <p class="text-sm text-ink/60">{{ $t('accounting.import.intro') }}</p>

                <div>
                    <InputLabel for="account_id" :value="$t('accounting.import.account')" />
                    <select
                        id="account_id"
                        v-model="form.account_id"
                        class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                    >
                        <option :value="null">{{ $t('accounting.import.pick_account') }}</option>
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <InputError :message="form.errors.account_id" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="file" :value="$t('accounting.import.file')" />
                    <input
                        id="file"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        class="mt-1 block w-full text-sm text-ink/70 file:mr-4 file:rounded-lg file:border-0 file:bg-ink/5 file:px-4 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-ink/10"
                        @input="form.file = $event.target.files[0]"
                    />
                    <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="mt-2 w-full">
                        {{ form.progress.percentage }}%
                    </progress>
                    <InputError :message="form.errors.file" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <PrimaryButton :disabled="form.processing || !form.file || !form.account_id">
                        <ArrowUpTrayIcon class="mr-1 h-4 w-4" /> {{ $t('accounting.import.upload') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
