<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { board, weeklyPlan, program, help } from '@/routes';
import { index as childrenIndex } from '@/routes/children';
import { index as excursionsIndex } from '@/routes/excursions';
import { index as pollsIndex } from '@/routes/polls';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? '');
const isStaff = computed(() => user.value?.role === 'staff');

const tiles = computed(() => {
    const items = [
        {
            title: 'Heute',
            text: isStaff.value
                ? 'Wer geht heute wann? Abholungen abhaken.'
                : 'Wer geht heute wann?',
            href: board().url,
        },
        {
            title: 'Ausflüge',
            text: isStaff.value
                ? 'Ausflüge planen und Rückmeldungen sehen.'
                : 'Für Ausflüge deines Kindes abstimmen.',
            href: isStaff.value ? excursionsIndex().url : pollsIndex().url,
        },
        {
            title: 'Abholplan',
            text: 'Abholzeiten, Essen und Aktivitäten der Woche.',
            href: weeklyPlan().url,
        },
    ];

    if (isStaff.value) {
        items.push({
            title: 'Programm',
            text: 'Mittagessen, Aktivität und Hausaufgaben eintragen.',
            href: program().url,
        });
    }

    items.push({
        title: isStaff.value ? 'Kinder' : 'Meine Kinder',
        text: isStaff.value
            ? 'Kinder und Stammpläne verwalten.'
            : 'Stammplan deines Kindes pflegen.',
        href: childrenIndex().url,
    });

    return items;
});
</script>

<template>
    <Head title="Start" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">
                Hallo {{ userName }} 👋
            </h2>
        </template>

        <div class="space-y-3">
            <Link
                v-for="tile in tiles"
                :key="tile.title"
                :href="tile.href"
                class="flex items-center justify-between rounded-2xl bg-white p-5 shadow-sm transition hover:shadow active:scale-[0.99]"
            >
                <div>
                    <h3 class="font-semibold text-hort-navy">{{ tile.title }}</h3>
                    <p class="mt-1 text-sm text-hort-navy/60">{{ tile.text }}</p>
                </div>
                <span class="text-2xl text-hort-teal-dark">→</span>
            </Link>
        </div>

        <p class="mt-6 text-center text-sm text-hort-navy/60">
            Neu hier oder unsicher?
            <Link :href="help().url" class="font-medium text-hort-teal-dark underline hover:text-hort-navy">
                So funktioniert der Hort-Manager
            </Link>
        </p>
    </AuthenticatedLayout>
</template>
