<script setup>
import { create as excursionsCreate } from '@/routes/excursions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ExcursionCard from './Partials/ExcursionCard.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    upcoming: { type: Array, default: () => [] },
    past: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash?.status);
</script>

<template>
    <Head title="Ausflüge" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Ausflüge</h2>
        </template>

        <div class="space-y-6">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <!-- How it works -->
            <p class="rounded-2xl bg-white p-4 text-sm text-hort-navy/70 shadow-sm">
                Plane einen Ausflug und lege eine Frist fest – alle Eltern werden
                automatisch gefragt, ob ihr Kind mitkommt. Am Ausflugstag
                erscheinen die zugesagten Kinder im Tagesboard.
            </p>

            <Link
                :href="excursionsCreate().url"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-hort-teal px-6 py-4 text-base font-semibold text-hort-navy shadow-sm transition hover:bg-hort-teal-dark active:scale-[0.99]"
            >
                <span class="text-xl leading-none">+</span> Ausflug planen
            </Link>

            <!-- Upcoming -->
            <section class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-hort-navy/50">
                    Anstehende Ausflüge
                </h3>

                <ul v-if="upcoming.length" class="space-y-3">
                    <ExcursionCard
                        v-for="excursion in upcoming"
                        :key="excursion.id"
                        :excursion="excursion"
                    />
                </ul>
                <p
                    v-else
                    class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
                >
                    Aktuell sind keine Ausflüge geplant. Lege oben den ersten an.
                </p>
            </section>

            <!-- History -->
            <section v-if="past.length" class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-hort-navy/50">
                    Vergangene Ausflüge
                </h3>
                <p class="-mt-1 text-xs text-hort-navy/45">
                    Bereits stattgefundene Ausflüge mit den Kindern, die dabei
                    waren.
                </p>

                <ul class="space-y-3">
                    <ExcursionCard
                        v-for="excursion in past"
                        :key="excursion.id"
                        :excursion="excursion"
                        past
                    />
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
