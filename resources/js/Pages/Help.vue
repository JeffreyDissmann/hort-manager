<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import HelpArticle from '@/Components/HelpArticle.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { login } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Reachable both before login and from inside the app.
const loggedIn = computed(() => !!usePage().props.auth?.user);
</script>

<template>
    <Head :title="$t('help.title')" />

    <!-- Logged in: show inside the normal app shell. -->
    <AuthenticatedLayout v-if="loggedIn">
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">{{ $t('help.header') }}</h2>
        </template>

        <HelpArticle />
    </AuthenticatedLayout>

    <!-- Guest: standalone page with a way back to the login. -->
    <div v-else class="min-h-screen bg-hort-sand">
        <header class="mx-auto flex max-w-3xl items-center justify-between px-4 py-5">
            <Link :href="login().url" class="flex items-center gap-2">
                <ApplicationLogo class="h-9 w-9" />
                <span class="font-semibold text-hort-navy">Hort-Manager</span>
            </Link>
            <Link
                :href="login().url"
                class="rounded-xl bg-hort-teal px-4 py-2 text-sm font-semibold text-hort-navy transition hover:bg-hort-teal-dark"
            >
                {{ $t('help.to_login') }}
            </Link>
        </header>

        <main class="mx-auto max-w-3xl px-4 pb-16">
            <HelpArticle />
        </main>
    </div>
</template>
