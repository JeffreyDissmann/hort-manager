<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    children: {
        type: Array,
        default: () => [],
    },
});

const flash = computed(() => usePage().props.flash?.status);

function formatDate(value) {
    if (!value) {
        return null;
    }
    const [year, month, day] = value.split('-');
    return `${day}.${month}.${year}`;
}

function destroy(child) {
    if (
        confirm(`„${child.name}“ wirklich löschen? Der Stammplan geht verloren.`)
    ) {
        router.delete(route('children.destroy', child.id));
    }
}
</script>

<template>
    <Head title="Kinder" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Kinder</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <Link
                :href="route('children.create')"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-hort-teal px-6 py-4 text-base font-semibold text-hort-navy shadow-sm transition hover:bg-hort-teal-dark active:scale-[0.99]"
            >
                <span class="text-xl leading-none">+</span> Kind hinzufügen
            </Link>

            <ul v-if="children.length" class="space-y-3">
                <li
                    v-for="child in children"
                    :key="child.id"
                    class="rounded-2xl bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-hort-navy">
                                {{ child.name }}
                            </p>
                            <p
                                v-if="formatDate(child.date_of_birth)"
                                class="mt-0.5 text-sm text-hort-navy/50"
                            >
                                * {{ formatDate(child.date_of_birth) }}
                            </p>
                            <p
                                v-if="child.note"
                                class="mt-1 line-clamp-2 text-sm text-hort-navy/70"
                            >
                                {{ child.note }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="destroy(child)"
                            class="shrink-0 rounded-lg p-2 text-hort-navy/30 transition hover:bg-red-50 hover:text-red-600"
                            aria-label="Kind löschen"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.8"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                                />
                            </svg>
                        </button>
                    </div>

                    <Link
                        :href="route('children.edit', child.id)"
                        class="mt-3 flex items-center justify-center gap-1 rounded-xl border-2 border-hort-navy/10 py-2.5 text-sm font-semibold text-hort-navy transition hover:border-hort-teal hover:bg-hort-teal/10"
                    >
                        Stammplan bearbeiten
                    </Link>
                </li>
            </ul>

            <p
                v-else
                class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
            >
                Noch keine Kinder angelegt. Lege das erste Kind an, um seinen
                Stammplan festzulegen.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
