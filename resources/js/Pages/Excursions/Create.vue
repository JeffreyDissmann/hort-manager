<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ExcursionFields from './Partials/ExcursionFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    allChildren: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    date: '',
    depart_at: '',
    return_at: '',
    note: '',
    children: [],
});

function submit() {
    form.post(route('excursions.store'));
}
</script>

<template>
    <Head title="Ausflug planen" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Ausflug planen</h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-2xl bg-white p-6 shadow-sm"
            >
                <ExcursionFields :form="form" :all-children="allChildren" />

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="route('excursions.index')"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        Abbrechen
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        Speichern
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
