<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    children: {
        type: Array,
        default: () => [],
    },
});

const flash = computed(() => usePage().props.flash?.status);

const weekdayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

function formatDate(value) {
    if (!value) {
        return '–';
    }
    const [year, month, day] = value.split('-');
    return `${day}.${month}.${year}`;
}

function destroy(child) {
    if (confirm(`„${child.name}“ wirklich löschen? Der Stammplan geht verloren.`)) {
        router.delete(route('children.destroy', child.id));
    }
}
</script>

<template>
    <Head title="Kinder" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Kinder
                </h2>
                <Link :href="route('children.create')">
                    <PrimaryButton>Kind hinzufügen</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="flash"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ flash }}
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table
                        v-if="children.length"
                        class="min-w-full divide-y divide-gray-200"
                    >
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-3">Name</th>
                                <th class="px-6 py-3">Geburtsdatum</th>
                                <th class="px-6 py-3">Hinweise</th>
                                <th class="px-6 py-3 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="child in children" :key="child.id">
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                    {{ child.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-gray-600">
                                    {{ formatDate(child.date_of_birth) }}
                                </td>
                                <td class="max-w-xs truncate px-6 py-4 text-gray-600">
                                    {{ child.note || '–' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <Link
                                            :href="route('children.edit', child.id)"
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                                        >
                                            Stammplan bearbeiten
                                        </Link>
                                        <DangerButton @click="destroy(child)">
                                            Löschen
                                        </DangerButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-else class="p-6 text-gray-500">
                        Noch keine Kinder angelegt. Lege das erste Kind an, um
                        seinen Stammplan festzulegen.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
