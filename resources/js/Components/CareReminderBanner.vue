<script setup>
// Nudges a parent to answer an open Ferienbetreuung. Fed by the shared `pendingCare`
// prop — a family that picked „keine Tage" has answered, so they aren't chased.
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
// Parents sign up on „Ausflüge & Ferien" — /care would only redirect them there.
import { index as pollsIndex } from '@/routes/polls';

const page = usePage();

// Not on the page it points at: „Tage auswählen" would lead nowhere, and the sheet
// itself is right there, saying the same thing per child.
const periods = computed(() =>
    page.component === 'Excursions/Poll' ? [] : (page.props.pendingCare ?? []),
);
const locale = computed(() => page.props.locale || 'de');

function deadlineLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'long',
    });
}
</script>

<template>
    <div
        v-if="periods.length"
        data-testid="care-reminder"
        class="rounded-2xl border border-hort-teal/50 bg-hort-teal/10 px-4 py-3 text-sm text-ink"
    >
        <p class="font-semibold">🏖️ {{ $t('care_reminder.title') }}</p>

        <p v-for="period in periods" :key="period.id" class="mt-0.5 text-ink/70">
            {{ period.name }} · {{ period.children.join(', ') }}
            <span v-if="period.deadline" class="font-medium">
                · {{ $t('care_reminder.until', { date: deadlineLabel(period.deadline) }) }}
            </span>
        </p>

        <div class="mt-2">
            <Link
                :href="pollsIndex().url"
                class="inline-block rounded-lg bg-hort-teal-dark px-3 py-1.5 font-semibold text-white transition hover:brightness-110"
            >
                {{ $t('care_reminder.action') }}
            </Link>
        </div>
    </div>
</template>
