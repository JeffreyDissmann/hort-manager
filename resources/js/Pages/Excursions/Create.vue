<script setup>
import { store as excursionsStore, index as excursionsIndex } from '@/routes/excursions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ExcursionFields from './Partials/ExcursionFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    suggestedDate: { type: String, default: '' },
});

const form = useForm({
    name: '',
    date: props.suggestedDate || '',
    depart_at: '14:30',
    return_at: '17:00',
    rsvp_deadline: '',
    note: '',
});

const canSubmit = computed(
    () => form.name && form.date && form.rsvp_deadline,
);

function submit() {
    form.post(excursionsStore().url);
}
</script>

<template>
    <Head :title="$t('excursions.plan_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">{{ $t('excursions.plan_title') }}</h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-2xl bg-white p-6 shadow-sm"
            >
                <ExcursionFields :form="form" :suggested-date="suggestedDate" />

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="excursionsIndex().url"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        {{ $t('common.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing || !canSubmit">
                        {{ $t('common.save') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
