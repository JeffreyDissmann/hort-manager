<script setup>
import { edit as excursionsEdit, destroy as excursionsDestroy } from '@/routes/excursions';
import { TrashIcon } from '@heroicons/vue/24/outline';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    excursion: { type: Object, required: true },
    past: { type: Boolean, default: false },
});

function formatDate(value) {
    if (!value) {
        return '';
    }
    const [year, month, day] = value.split('-');
    return `${day}.${month}.${year}`;
}

function timeRange(e) {
    if (e.depart_at && e.return_at) {
        return `${e.depart_at}–${e.return_at} Uhr`;
    }
    return e.depart_at ? `ab ${e.depart_at} Uhr` : '';
}

function destroy() {
    if (confirm(`Ausflug „${props.excursion.name}“ wirklich löschen?`)) {
        router.delete(excursionsDestroy(props.excursion.id).url);
    }
}
</script>

<template>
    <li
        class="rounded-2xl p-4 shadow-sm"
        :class="past ? 'bg-white/70' : 'bg-white'"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p
                    class="font-semibold"
                    :class="past ? 'text-hort-navy/80' : 'text-hort-navy'"
                >
                    {{ excursion.name }}
                </p>
                <p class="mt-0.5 text-sm text-hort-navy/60">
                    {{ formatDate(excursion.date) }}
                    <span v-if="timeRange(excursion)">
                        · {{ timeRange(excursion) }}
                    </span>
                </p>
            </div>
            <button
                type="button"
                @click="destroy"
                class="shrink-0 rounded-lg p-2 text-hort-navy/30 transition hover:bg-red-50 hover:text-red-600"
                aria-label="Ausflug löschen"
            >
                <TrashIcon class="h-5 w-5" />
            </button>
        </div>

        <!-- Poll status -->
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold">
            <span class="text-hort-teal-dark">
                ✓ {{ excursion.joining_count }} {{ past ? 'waren dabei' : 'dabei' }}
            </span>
            <span v-if="!past" class="text-amber-600">
                offen {{ excursion.pending_count }}
            </span>
            <span v-if="excursion.declined_count" class="text-hort-purple">
                ✗ {{ excursion.declined_count }}
            </span>
            <span v-if="!past" class="font-medium text-hort-navy/40">
                ·
                <template v-if="excursion.poll_open">
                    Abstimmung bis {{ formatDate(excursion.rsvp_deadline) }}
                </template>
                <template v-else>Abstimmung beendet</template>
            </span>
        </div>

        <div
            v-if="excursion.participants.length"
            class="mt-2 flex flex-wrap gap-1.5"
        >
            <span
                v-for="name in excursion.participants"
                :key="name"
                class="rounded-md bg-hort-teal/15 px-2 py-0.5 text-xs font-medium text-hort-teal-dark"
            >
                {{ name }}
            </span>
        </div>

        <Link
            :href="excursionsEdit(excursion.id).url"
            class="mt-3 flex items-center justify-center gap-1 rounded-xl border-2 border-hort-navy/10 py-2.5 text-sm font-semibold text-hort-navy transition hover:border-hort-teal hover:bg-hort-teal/10"
        >
            {{ past ? 'Ansehen' : 'Bearbeiten' }}
        </Link>
    </li>
</template>
