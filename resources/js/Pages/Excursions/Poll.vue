<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    upcoming: { type: Array, default: () => [] },
    past: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash?.status);

const weekdays = [
    'Sonntag', 'Montag', 'Dienstag', 'Mittwoch',
    'Donnerstag', 'Freitag', 'Samstag',
];

function parseDate(value) {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function formatDate(value) {
    if (!value) {
        return '';
    }
    const [year, month, day] = value.split('-');
    return `${day}.${month}.${year}`;
}

function longDate(value) {
    if (!value) {
        return '';
    }
    return `${weekdays[parseDate(value).getDay()]}, ${formatDate(value)}`;
}

function daysUntil(value) {
    if (!value) {
        return null;
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((parseDate(value) - today) / 86400000);
}

function deadlineHint(value) {
    const days = daysUntil(value);
    if (days === null) {
        return '';
    }
    if (days <= 0) {
        return 'Heute ist der letzte Tag zum Antworten';
    }
    if (days === 1) {
        return 'Bitte bis morgen antworten';
    }
    return `Bitte bis ${formatDate(value)} antworten (noch ${days} Tage)`;
}

function timeRange(e) {
    if (e.depart_at && e.return_at) {
        return `${e.depart_at}–${e.return_at} Uhr`;
    }
    return e.depart_at ? `ab ${e.depart_at} Uhr` : '';
}

function answer(excursion, child, response) {
    router.patch(
        route('polls.update', excursion.id),
        { child_id: child.id, response },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Ausflüge – Abstimmung" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Ausflüge</h2>
        </template>

        <div class="space-y-8">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <!-- Upcoming excursions (answerable while the poll is open) -->
            <section class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-hort-navy/50">
                    Anstehende Ausflüge
                </h3>

                <ul v-if="upcoming.length" class="space-y-3">
                    <li
                        v-for="excursion in upcoming"
                        :key="excursion.id"
                        class="rounded-2xl bg-white p-4 shadow-sm"
                    >
                        <p class="font-semibold text-hort-navy">
                            🚌 {{ excursion.name }}
                        </p>
                        <p class="mt-0.5 text-sm text-hort-navy/60">
                            {{ longDate(excursion.date) }}
                        </p>

                        <dl
                            v-if="excursion.depart_at || excursion.return_at"
                            class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm"
                        >
                            <div v-if="excursion.depart_at" class="flex gap-1.5">
                                <dt class="text-hort-navy/40">Abfahrt</dt>
                                <dd class="font-semibold text-hort-navy">
                                    {{ excursion.depart_at }} Uhr
                                </dd>
                            </div>
                            <div v-if="excursion.return_at" class="flex gap-1.5">
                                <dt class="text-hort-navy/40">Rückkehr</dt>
                                <dd class="font-semibold text-hort-navy">
                                    {{ excursion.return_at }} Uhr
                                </dd>
                            </div>
                        </dl>

                        <p
                            v-if="excursion.note"
                            class="mt-2 rounded-xl bg-hort-teal/10 px-3 py-2 text-sm text-hort-navy/80"
                        >
                            📋 {{ excursion.note }}
                        </p>

                        <p
                            v-if="excursion.poll_open && excursion.rsvp_deadline"
                            class="mt-2 text-xs font-semibold text-amber-600"
                        >
                            ⏰ {{ deadlineHint(excursion.rsvp_deadline) }}
                        </p>
                        <p
                            v-else-if="!excursion.poll_open"
                            class="mt-2 text-xs font-medium text-hort-navy/40"
                        >
                            Abstimmung beendet
                        </p>

                        <div class="mt-3 space-y-2">
                            <div
                                v-for="child in excursion.children"
                                :key="child.id"
                                class="flex items-center justify-between gap-3 rounded-xl bg-hort-sand p-3"
                            >
                                <div class="min-w-0">
                                    <span class="font-medium text-hort-navy">
                                        {{ child.name }}
                                    </span>
                                    <span
                                        v-if="child.response === null"
                                        class="ml-1 text-xs font-semibold text-amber-600"
                                    >
                                        – noch offen
                                    </span>
                                    <span
                                        v-else-if="child.response === true"
                                        class="ml-1 text-xs font-semibold text-hort-teal-dark"
                                    >
                                        – zugesagt ✓
                                    </span>
                                    <span
                                        v-else
                                        class="ml-1 text-xs font-semibold text-hort-purple"
                                    >
                                        – abgesagt
                                    </span>
                                </div>
                                <div
                                    v-if="excursion.poll_open"
                                    class="flex shrink-0 gap-2"
                                >
                                    <button
                                        type="button"
                                        class="rounded-lg px-4 py-2 text-sm font-semibold transition active:scale-[0.97]"
                                        :class="child.response === true
                                            ? 'bg-hort-teal text-hort-navy'
                                            : 'bg-white text-hort-navy/60 ring-1 ring-hort-navy/10 hover:bg-hort-teal/20'"
                                        @click="answer(excursion, child, true)"
                                    >
                                        Kommt mit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-4 py-2 text-sm font-semibold transition active:scale-[0.97]"
                                        :class="child.response === false
                                            ? 'bg-hort-purple text-white'
                                            : 'bg-white text-hort-navy/60 ring-1 ring-hort-navy/10 hover:bg-hort-purple/15'"
                                        @click="answer(excursion, child, false)"
                                    >
                                        Nicht dabei
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>

                <p
                    v-else
                    class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
                >
                    Aktuell sind keine Ausflüge geplant.
                </p>
            </section>

            <!-- Past excursions (read-only) -->
            <section v-if="past.length" class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-hort-navy/50">
                    Vergangene Ausflüge
                </h3>

                <ul class="space-y-3">
                    <li
                        v-for="excursion in past"
                        :key="excursion.id"
                        class="rounded-2xl bg-white/70 p-4 shadow-sm"
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="font-semibold text-hort-navy/80">
                                {{ excursion.name }}
                            </p>
                            <p class="shrink-0 text-sm text-hort-navy/50">
                                {{ formatDate(excursion.date) }}
                            </p>
                        </div>
                        <p
                            v-if="timeRange(excursion)"
                            class="mt-0.5 text-sm text-hort-navy/50"
                        >
                            {{ timeRange(excursion) }}
                        </p>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <span
                                v-for="child in excursion.children"
                                :key="child.id"
                                class="rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="child.response === true
                                    ? 'bg-hort-teal/15 text-hort-teal-dark'
                                    : child.response === false
                                      ? 'bg-hort-purple/10 text-hort-purple'
                                      : 'bg-hort-navy/5 text-hort-navy/40'"
                            >
                                {{ child.name }}:
                                {{
                                    child.response === true
                                        ? 'dabei'
                                        : child.response === false
                                          ? 'nicht dabei'
                                          : 'keine Rückmeldung'
                                }}
                            </span>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
