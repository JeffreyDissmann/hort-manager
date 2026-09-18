<script setup>
// „Abholzeiten prüfen": every pickup of this parent's children that lands inside the
// Hausaufgabenzeit, a timed Aktivität or an Ausflug — the standing summary of what the
// board and the Wochenplan flag per day. Fed by the shared `pickupClashes` prop, and
// built like the Ferien/Ausflug reminder: a line per finding, then one way to act.
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { weeklyPlan } from '@/routes';
import { edit as childrenEdit } from '@/routes/children';
import { t } from '@/i18n';

const page = usePage();

const clashes = computed(() => page.props.pickupClashes ?? { recurring: [], dated: [] });

/**
 * „Gesehen" for this browser session only — nothing is stored server-side, so the
 * summary is back at the next sign-in. What's remembered is the findings themselves,
 * not just „dismissed": a *new* clash brings the banner back, while fixing one of the
 * others doesn't (that would nag for doing the right thing). sessionStorage can throw
 * in a private window, so every access is guarded — failing to read it shows the banner.
 */
const STORAGE_KEY = 'pickup-clashes-dismissed';

const keys = computed(() =>
    [...clashes.value.recurring, ...clashes.value.dated]
        .map((c) => [c.child_id, c.date ?? `w${c.weekday}`, c.time, c.kind, c.from].join('-')),
);

const dismissedKeys = ref(readDismissed());

function readDismissed() {
    try {
        return JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

function dismiss() {
    dismissedKeys.value = keys.value;
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(keys.value));
    } catch {
        // No session storage (private window) — hidden for this page view, back on the next.
    }
}

// Anything the family hasn't waved away yet brings the whole summary back, so a new
// clash is never hidden behind an earlier „gesehen".
const visible = computed(() => keys.value.some((key) => !dismissedKeys.value.includes(key)));

// Mo–Fr, in the app's own short form (the Hort has no weekend).
const WEEKDAYS = ['weekly.weekday.mon', 'weekly.weekday.tue', 'weekly.weekday.wed', 'weekly.weekday.thu', 'weekly.weekday.fri'];

/** „Mi, 24.09." — the date a family recognises, not an ISO string. */
function dayLabel(date) {
    const [year, month, day] = date.split('-').map(Number);
    const weekday = new Date(year, month - 1, day).getDay();

    return `${t(WEEKDAYS[weekday - 1] ?? WEEKDAYS[0])}, ${String(day).padStart(2, '0')}.${String(month).padStart(2, '0')}.`;
}

/** What the pickup runs into: the homework slot, a named activity, or a trip. */
function what(clash) {
    return t(`weekly.clashes.in_${clash.kind}`, { name: clash.name ?? '', from: clash.from, to: clash.to });
}

// Each finding is fixed in a different place, so each line links to its own: a single
// day in that week's Wochenplan, a recurring one in the child's Stammplan. The button
// below leads to the nearest day — or to the Stammplan when only that collides.
const dayLink = (date) => weeklyPlan({ query: { week: date } }).url;
const standardLink = (childId) => childrenEdit(childId).url;

const action = computed(() => {
    const next = clashes.value.dated[0];
    if (next) {
        return { href: dayLink(next.date), label: t('weekly.clashes.action') };
    }

    const recurring = clashes.value.recurring[0];

    return recurring
        ? { href: standardLink(recurring.child_id), label: t('weekly.clashes.action_standard') }
        : { href: weeklyPlan().url, label: t('weekly.clashes.action') };
});
</script>

<template>
    <div
        v-if="visible"
        data-testid="pickup-clash-banner"
        class="relative rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 pr-10 text-sm text-amber-800"
    >
        <button
            type="button"
            data-testid="pickup-clash-dismiss"
            class="absolute right-2 top-2 rounded-lg p-1 text-amber-700/70 transition hover:bg-amber-100 hover:text-amber-900"
            :aria-label="$t('common.close')"
            @click="dismiss"
        >
            <XMarkIcon class="h-4 w-4" />
        </button>

        <p class="font-semibold">⚠️ {{ $t('weekly.clashes.title') }}</p>

        <!-- The Stammplan itself collides — that repeats every week until it changes,
             so the line leads to the child's Stammplan rather than to a single day. -->
        <p v-for="(clash, i) in clashes.recurring" :key="`r${i}`" class="mt-0.5 text-amber-900/80">
            <Link :href="standardLink(clash.child_id)" class="font-medium underline-offset-2 hover:underline">
                {{ clash.child }}
            </Link>
            · {{ $t('weekly.clashes.every_weekday', { day: $t(WEEKDAYS[clash.weekday - 1]) }) }}
            · {{ $t('weekly.clashes.pickup_at', { time: clash.time }) }} {{ what(clash) }}
        </p>

        <!-- One day: its date opens exactly that week, so several findings across
             different weeks are each one click away. -->
        <p v-for="(clash, i) in clashes.dated" :key="`d${i}`" class="mt-0.5 text-amber-900/80">
            <span class="font-medium">{{ clash.child }}</span>
            ·
            <Link :href="dayLink(clash.date)" class="font-medium underline-offset-2 hover:underline">
                {{ dayLabel(clash.date) }}
            </Link>
            · {{ $t('weekly.clashes.pickup_at', { time: clash.time }) }} {{ what(clash) }}
        </p>

        <div class="mt-2">
            <Link
                :href="action.href"
                data-testid="pickup-clash-action"
                class="inline-block rounded-lg bg-amber-600 px-3 py-1.5 font-semibold text-white transition hover:bg-amber-700"
            >
                {{ action.label }}
            </Link>
        </div>
    </div>
</template>
