<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ExcursionFields from './Partials/ExcursionFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    excursion: { type: Object, required: true },
    childIds: { type: Array, default: () => [] },
    allChildren: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.excursion.name,
    date: props.excursion.date ?? '',
    depart_at: props.excursion.depart_at ?? '',
    return_at: props.excursion.return_at ?? '',
    note: props.excursion.note ?? '',
    children: [...props.childIds],
});

function submit() {
    form.put(route('excursions.update', props.excursion.id));
}
</script>

<template>
    <Head :title="`${excursion.name} bearbeiten`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">
                Ausflug bearbeiten
            </h2>
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
