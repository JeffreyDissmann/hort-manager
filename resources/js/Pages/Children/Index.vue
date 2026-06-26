<script setup>
import { create as childrenCreate, edit as childrenEdit, destroy as childrenDestroy } from '@/routes/children';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { TrashIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    children: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: false,
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
        router.delete(childrenDestroy(child.id).url);
    }
}
</script>

<template>
    <Head title="Kinder" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">
                {{ canManage ? 'Kinder' : 'Meine Kinder' }}
            </h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <Link
                v-if="canManage"
                :href="childrenCreate().url"
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
                            v-if="canManage"
                            type="button"
                            @click="destroy(child)"
                            class="shrink-0 rounded-lg p-2 text-hort-navy/30 transition hover:bg-red-50 hover:text-red-600"
                            aria-label="Kind löschen"
                        >
                            <TrashIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <Link
                        :href="childrenEdit(child.id).url"
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
                <template v-if="canManage">
                    Noch keine Kinder angelegt. Lege das erste Kind an, um seinen
                    Stammplan festzulegen.
                </template>
                <template v-else>
                    Dir ist noch kein Kind zugeordnet. Bitte wende dich an das
                    Hort-Team.
                </template>
            </p>
        </div>
    </AuthenticatedLayout>
</template>
