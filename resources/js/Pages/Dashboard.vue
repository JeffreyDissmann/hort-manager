<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? '');
const isStaff = computed(() => user.value?.role === 'staff');

const tiles = computed(() => [
    {
        title: 'Heute',
        text: isStaff.value
            ? 'Wer geht heute wann? Abholungen abhaken.'
            : 'Wer geht heute wann?',
        route: 'board',
    },
    {
        title: 'Wochenplan',
        text: 'Die Abholzeiten aller Kinder im Überblick.',
        route: 'weekly-plan',
    },
    {
        title: isStaff.value ? 'Kinder' : 'Meine Kinder',
        text: isStaff.value
            ? 'Kinder und Stammpläne verwalten.'
            : 'Stammplan deines Kindes pflegen.',
        route: 'children.index',
    },
]);
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
                :key="tile.route"
                :href="route(tile.route)"
                class="flex items-center justify-between rounded-2xl bg-white p-5 shadow-sm transition hover:shadow active:scale-[0.99]"
            >
                <div>
                    <h3 class="font-semibold text-hort-navy">{{ tile.title }}</h3>
                    <p class="mt-1 text-sm text-hort-navy/60">{{ tile.text }}</p>
                </div>
                <span class="text-2xl text-hort-teal-dark">→</span>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
