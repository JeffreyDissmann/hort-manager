<script setup>
import { dashboard, login, help } from '@/routes';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
    laravelVersion: { type: String, default: '' },
    phpVersion: { type: String, default: '' },
    // Set while the Hort is shut — then this screen leads with the holiday.
    closure: { type: Object, default: null },
    nextOpen: { type: Object, default: null },
});

const appName = computed(() => usePage().props.appName ?? 'Hort-Manager');
const user = computed(() => usePage().props.auth?.user);
const locale = computed(() => usePage().props.locale || 'de');

// One of a handful of holiday pictures, picked by the date rather than at random so
// the same shut day looks the same every time it's opened.
const closedEmoji = computed(() => {
    const emojis = ['🏖️', '🌻', '🎈', '🛶', '🪁', '⛺️'];
    const day = new Date();

    return emojis[(day.getFullYear() + day.getMonth() * 31 + day.getDate()) % emojis.length];
});

/** „Montag, 24. August" — the day the Hort opens again. */
const nextOpenLabel = computed(() =>
    props.nextOpen
        ? new Date(`${props.nextOpen.date}T00:00:00`).toLocaleDateString(locale.value, {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
          })
        : null,
);
</script>

<template>
    <Head :title="$t('welcome.title')" />

    <div
        class="relative flex min-h-[100dvh] flex-col overflow-hidden bg-canvas text-ink"
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

            <h1 class="mt-6 font-display text-5xl leading-tight text-ink">
                {{ appName }}
            </h1>

            <p class="mt-4 text-lg text-ink/70">
                {{ $t('welcome.tagline') }}
            </p>

            <!-- Urlaubsschirm. Borrowed from the Schülerladen's own site: a soft teal
                 section with a torn top edge rather than another rounded card. -->
            <section
                v-if="closure"
                data-testid="welcome-closure"
                class="mt-8 w-full overflow-hidden rounded-2xl text-hort-navy"
            >
                <svg
                    class="block h-4 w-full text-hort-teal/40"
                    viewBox="0 0 400 20"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <path
                        d="M0,14 C50,2 90,18 150,10 C210,2 250,18 310,9 C350,3 380,12 400,7 L400,20 L0,20 Z"
                        fill="currentColor"
                    />
                </svg>

                <div class="bg-hort-teal/40 px-6 pb-6 pt-2">
                    <p class="text-5xl leading-none" aria-hidden="true">{{ closedEmoji }}</p>
                    <p class="mt-3 font-display text-2xl">{{ $t('welcome.closed_title') }}</p>
                    <p class="mt-1 font-medium">{{ closure.name }}</p>
                    <p v-if="closure.note" class="mt-1 text-sm text-hort-navy/70">{{ closure.note }}</p>
                    <p class="mt-1 text-sm text-hort-navy/70">
                        {{
                            closure.days_left <= 1
                                ? $t('welcome.closed_days_left_one')
                                : $t('welcome.closed_days_left_many', { count: closure.days_left })
                        }}
                    </p>

                    <!-- „Wann geht's weiter?" — the question a shut Hort leaves open. A
                         Ferienbetreuung is called out, because being there means having
                         signed up for it. -->
                    <div class="mt-4 rounded-xl bg-canvas/80 px-4 py-3">
                        <template v-if="nextOpen">
                            <p class="font-semibold text-ink" data-testid="welcome-next-open">
                                {{ $t('welcome.next_open', { day: nextOpenLabel }) }}
                            </p>
                            <p
                                v-if="nextOpen.care"
                                class="mt-0.5 text-sm font-medium text-hort-teal-dark"
                            >
                                🏫 {{ $t('welcome.next_open_care') }}
                            </p>
                            <p v-else class="mt-0.5 text-sm text-ink/60">
                                {{ $t('welcome.next_open_normal') }}
                            </p>
                        </template>
                        <p v-else class="text-sm text-ink/60">{{ $t('welcome.next_open_unknown') }}</p>
                    </div>
                </div>
            </section>

            <div class="mt-10 w-full space-y-3">
                <template v-if="user">
                    <Link
                        :href="dashboard().url"
                        class="block w-full rounded-2xl bg-hort-teal px-6 py-4 text-lg font-semibold text-hort-navy shadow-sm transition hover:bg-hort-teal-dark active:scale-[0.99]"
                    >
                        {{ $t('welcome.to_app') }}
                    </Link>
                </template>

                <template v-else-if="canLogin">
                    <Link
                        :href="login().url"
                        class="block w-full rounded-2xl bg-hort-teal px-6 py-4 text-lg font-semibold text-hort-navy shadow-sm transition hover:bg-hort-teal-dark active:scale-[0.99]"
                    >
                        {{ $t('welcome.sign_in') }}
                    </Link>
                </template>

                <Link
                    :href="help().url"
                    class="block w-full rounded-2xl px-6 py-3 text-base font-medium text-ink/70 transition hover:bg-ink/5"
                >
                    {{ $t('welcome.how_it_works') }}
                </Link>
            </div>
        </main>

        <footer
            class="relative z-10 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-center text-sm text-ink/50"
        >
            {{ $t('welcome.made_with_love') }}
        </footer>
    </div>
</template>
