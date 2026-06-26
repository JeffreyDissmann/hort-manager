<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    excursions: {
        type: Array,
        default: () => [],
    },
});

const flash = computed(() => usePage().props.flash?.status);

function formatDate(value) {
    if (!value) {
        return '';
    }
    const [year, month, day] = value.split('-');
    return `${day}.${month}.${year}`;
}

function timeRange(e) {
    if (e.depart_at && e.return_at) {
        return `${e.depart_at}–${e.return_at} Uhr`;
    }
    return e.depart_at ? `ab ${e.depart_at} Uhr` : '';
}

function destroy(excursion) {
    if (confirm(`Ausflug „${excursion.name}“ wirklich löschen?`)) {
        router.delete(route('excursions.destroy', excursion.id));
    }
}
</script>

<template>
    <Head title="Ausflüge" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Ausflüge</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <Link
                :href="route('excursions.create')"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-hort-teal px-6 py-4 text-base font-semibold text-hort-navy shadow-sm transition hover:bg-hort-teal-dark active:scale-[0.99]"
            >
                <span class="text-xl leading-none">+</span> Ausflug planen
            </Link>

            <ul v-if="excursions.length" class="space-y-3">
                <li
                    v-for="excursion in excursions"
                    :key="excursion.id"
                    class="rounded-2xl bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-hort-navy">
                                {{ excursion.name }}
                            </p>
                            <p class="mt-0.5 text-sm text-hort-navy/60">
                                {{ formatDate(excursion.date) }}
                                <span v-if="timeRange(excursion)">
                                    · {{ timeRange(excursion) }}
                                </span>
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="destroy(excursion)"
                            class="shrink-0 rounded-lg p-2 text-hort-navy/30 transition hover:bg-red-50 hover:text-red-600"
                            aria-label="Ausflug löschen"
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

                    <div
                        v-if="excursion.children.length"
                        class="mt-2 flex flex-wrap gap-1.5"
                    >
                        <span
                            v-for="name in excursion.children"
                            :key="name"
                            class="rounded-md bg-hort-purple/10 px-2 py-0.5 text-xs font-medium text-hort-purple"
                        >
                            {{ name }}
                        </span>
                    </div>
                    <p v-else class="mt-2 text-xs text-hort-navy/40">
                        Noch keine Kinder zugeordnet.
                    </p>

                    <Link
                        :href="route('excursions.edit', excursion.id)"
                        class="mt-3 flex items-center justify-center gap-1 rounded-xl border-2 border-hort-navy/10 py-2.5 text-sm font-semibold text-hort-navy transition hover:border-hort-teal hover:bg-hort-teal/10"
                    >
                        Bearbeiten
                    </Link>
                </li>
            </ul>

            <p
                v-else
                class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
            >
                Noch keine Ausflüge geplant.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
