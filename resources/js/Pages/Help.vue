<script setup>
// Hilfe: a topic overview (/help) plus one page per chapter (/help/holidays …).
// Split up because the app now covers a lot of ground — one long article was
// hard to find anything in.
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HelpBody from '@/Components/Help/HelpBody.vue';
import { help, login } from '@/routes';
import { t } from '@/i18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // Slug of the chapter to show, or null for the overview.
    topic: { type: String, default: null },
});

// Reachable both before login and from inside the app.
const loggedIn = computed(() => !!usePage().props.auth?.user);

const heading = computed(() => (props.topic ? t(`help.topics.${props.topic}.title`) : t('help.header')));
</script>

<template>
    <Head :title="topic ? `${$t('help.title')} · ${heading}` : $t('help.title')" />

    <!-- Logged in: show inside the normal app shell. -->
    <AuthenticatedLayout v-if="loggedIn">
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ heading }}</h2>
        </template>

        <HelpBody :topic="topic" />
    </AuthenticatedLayout>

    <!-- Guest: standalone page with a way back to the login. -->
    <div v-else class="min-h-screen bg-canvas">
        <header class="mx-auto flex max-w-3xl items-center justify-between px-4 py-5">
            <Link :href="help().url" class="flex items-center gap-2">
                <ApplicationLogo class="h-9 w-9" />
                <span class="font-semibold text-ink">Hort-Manager</span>
            </Link>
            <Link
                :href="login().url"
                class="rounded-xl bg-hort-teal px-4 py-2 text-sm font-semibold text-hort-navy transition hover:bg-hort-teal-dark"
            >
                {{ $t('help.to_login') }}
            </Link>
        </header>

        <main class="mx-auto max-w-3xl px-4 pb-16">
            <HelpBody :topic="topic" />
        </main>
    </div>
</template>
