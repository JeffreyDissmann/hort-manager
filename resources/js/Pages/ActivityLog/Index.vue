<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { t } from '@/i18n';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    // Laravel paginator (data + prev/next_page_url).
    activities: { type: Object, required: true },
});

// create/update/delete show a subject noun ("Kind · Emma"); action events
// (picked_up, adjusted, …) are conveyed by the badge alone, so just show the label.
const CRUD_EVENTS = ['created', 'updated', 'deleted'];
const KNOWN_EVENTS = [
    ...CRUD_EVENTS, 'picked_up', 'sent_home', 'present',
    'adjusted', 'reset', 'rsvp_yes', 'rsvp_no', 'guardians',
    'companion_yes', 'companion_no',
];

function eventLabel(event) {
    return KNOWN_EVENTS.includes(event) ? t(`activity.events.${event}`) : (event ?? '');
}

function title(entry) {
    if (CRUD_EVENTS.includes(entry.event) && entry.subject) {
        const noun = t(`activity.subjects.${entry.subject}`);
        return entry.description ? `${noun} · ${entry.description}` : noun;
    }
    return entry.description;
}

function fieldLabel(field) {
    const key = `activity.fields.${field}`;
    const label = t(key);
    return label === key ? field : label; // t() returns the key when untranslated
}

// Translate known enum values (picked_up, sent_home, staff, …); leave data as-is.
function valueLabel(value) {
    if (value === null || value === undefined) {
        return '—';
    }
    const key = `activity.values.${value}`;
    const label = t(key);
    return label === key ? value : label;
}

function eventClass(event) {
    if (['created', 'picked_up', 'rsvp_yes', 'companion_yes'].includes(event)) {
        return 'bg-hort-teal/15 text-hort-teal-dark';
    }
    if (['deleted', 'reset', 'present', 'rsvp_no', 'companion_no'].includes(event)) {
        return 'bg-red-100 text-red-700';
    }
    return 'bg-amber-100 text-amber-700'; // updated, adjusted, guardians, sent_home
}
</script>

<template>
    <Head :title="$t('activity.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('activity.header') }}</h2>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-6">
            <p class="mb-4 text-sm text-ink/60">{{ $t('activity.intro') }}</p>

            <p v-if="!activities.data.length" class="rounded-2xl bg-surface p-6 text-center text-ink/50">
                {{ $t('activity.empty') }}
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="entry in activities.data"
                    :key="entry.id"
                    class="rounded-2xl bg-surface p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span
                                class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="eventClass(entry.event)"
                            >
                                {{ eventLabel(entry.event) }}
                            </span>
                            <p v-if="title(entry)" class="mt-1 font-medium text-ink">{{ title(entry) }}</p>
                            <ul v-if="entry.changes.length" class="mt-1 space-y-0.5">
                                <li
                                    v-for="change in entry.changes"
                                    :key="change.field"
                                    class="text-xs text-ink/50"
                                >
                                    <span class="font-medium">{{ fieldLabel(change.field) }}</span>:
                                    <span v-if="change.old" class="line-through">{{ valueLabel(change.old) }}</span>
                                    <span v-if="change.old"> → </span>{{ valueLabel(change.new) }}
                                </li>
                            </ul>
                        </div>
                        <div class="shrink-0 text-right text-xs text-ink/50">
                            <p class="font-medium text-ink/70">{{ entry.causer ?? $t('activity.system') }}</p>
                            <p>{{ entry.at }}</p>
                        </div>
                    </div>
                </li>
            </ul>

            <div v-if="activities.data.length" class="mt-4 flex items-center justify-between text-sm">
                <Link
                    v-if="activities.prev_page_url"
                    :href="activities.prev_page_url"
                    class="text-hort-teal-dark hover:underline"
                >
                    ← {{ $t('activity.newer') }}
                </Link>
                <span v-else />
                <Link
                    v-if="activities.next_page_url"
                    :href="activities.next_page_url"
                    class="text-hort-teal-dark hover:underline"
                >
                    {{ $t('activity.older') }} →
                </Link>
                <span v-else />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
