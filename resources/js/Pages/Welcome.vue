<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
    laravelVersion: { type: String, default: '' },
    phpVersion: { type: String, default: '' },
});

const appName = computed(() => usePage().props.appName ?? 'Hort-Manager');
const user = computed(() => usePage().props.auth?.user);
</script>

<template>
    <Head title="Willkommen" />

    <div
        class="relative flex min-h-[100dvh] flex-col overflow-hidden bg-hort-sand text-hort-navy"
    >
        <!-- Playful background accents -->
        <div
            class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-hort-teal/30"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -bottom-20 -left-16 h-64 w-64 rounded-full bg-hort-purple/20"
            aria-hidden="true"
        />

        <main
            class="relative z-10 mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center px-6 py-12 text-center"
        >
            <ApplicationLogo class="h-20 w-20 drop-shadow-sm" />

            <h1 class="mt-6 font-display text-5xl leading-tight text-hort-navy">
                {{ appName }}
            </h1>

            <p class="mt-4 text-lg text-hort-navy/70">
                Stammplan, Abholzeiten und Ausflüge – alles an einem Ort. Für
                unser Hort-Team und die Eltern.
            </p>

            <div class="mt-10 w-full space-y-3">
                <template v-if="user">
                    <Link
                        :href="route('dashboard')"
                        class="block w-full rounded-2xl bg-hort-teal px-6 py-4 text-lg font-semibold text-hort-navy shadow-sm transition hover:bg-hort-teal-dark active:scale-[0.99]"
                    >
                        Zur App
                    </Link>
                </template>

                <template v-else-if="canLogin">
                    <Link
                        :href="route('login')"
                        class="block w-full rounded-2xl bg-hort-teal px-6 py-4 text-lg font-semibold text-hort-navy shadow-sm transition hover:bg-hort-teal-dark active:scale-[0.99]"
                    >
                        Anmelden
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="block w-full rounded-2xl border-2 border-hort-navy/15 bg-white px-6 py-4 text-lg font-semibold text-hort-navy shadow-sm transition hover:border-hort-navy/30 active:scale-[0.99]"
                    >
                        Registrieren
                    </Link>
                </template>
            </div>
        </main>

        <footer
            class="relative z-10 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-center text-sm text-hort-navy/50"
        >
            Mit ♥ für den Hort gemacht
        </footer>
    </div>
</template>
