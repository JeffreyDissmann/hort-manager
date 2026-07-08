<script setup>
import { t } from '@/i18n';

defineProps({
    // [{ id, day, child, companion, status: 'pickup'|'pending'|'confirmed'|'declined', actionable }]
    notes: { type: Array, default: () => [] },
});

defineEmits(['confirm']);

// Short status label + colour for a non-actionable note.
const statusLabels = {
    pickup: () => t('weekly.note_status_pickup'),
    pending: () => t('weekly.companion_pending'),
    confirmed: () => t('weekly.note_status_confirmed'),
    declined: () => t('weekly.companion_declined'),
};
const statusClasses = {
    pickup: 'bg-hort-teal/20 text-hort-teal-dark',
    pending: 'bg-hort-orange/15 text-hort-orange-dark',
    confirmed: 'bg-hort-teal/20 text-hort-teal-dark',
    declined: 'bg-red-100 text-red-700',
};
</script>

<template>
    <section v-if="notes.length" class="space-y-2">
        <h3 class="text-sm font-semibold text-ink/70">{{ $t('weekly.notes_heading') }}</h3>

        <div
            v-for="note in notes"
            :key="note.id"
            class="rounded-2xl p-4"
            :class="note.actionable ? 'bg-hort-orange/10' : 'bg-surface shadow-sm'"
        >
            <!-- The companion's family still has to confirm → ask them right here. -->
            <template v-if="note.actionable">
                <p class="text-sm text-ink">
                    {{ $t('weekly.companion_request', { child: note.child, companion: note.companion, day: note.day }) }}
                </p>
                <div class="mt-3 flex gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-hort-teal px-4 py-2 text-sm font-semibold text-hort-navy transition hover:bg-hort-teal-dark active:scale-[0.98]"
                        @click="$emit('confirm', note.id, true)"
                    >
                        {{ $t('weekly.companion_confirm') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-hort-orange px-4 py-2 text-sm font-semibold text-hort-navy transition hover:opacity-90 active:scale-[0.98]"
                        @click="$emit('confirm', note.id, false)"
                    >
                        {{ $t('weekly.companion_decline') }}
                    </button>
                </div>
            </template>

            <!-- Otherwise it's informational: the arrangement + its status. -->
            <p v-else class="text-sm text-ink">
                {{ $t('weekly.note_line', { child: note.child, day: note.day, companion: note.companion }) }}
                <span
                    class="ml-1 whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium"
                    :class="statusClasses[note.status]"
                >
                    {{ statusLabels[note.status]() }}
                </span>
            </p>
        </div>
    </section>
</template>
