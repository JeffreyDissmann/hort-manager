<script setup>
// One quiet line naming the Springerdienst — on the board for the day, on the
// Wochenplan per weekday. Own children are emphasised; an open slot reads „offen".
// Links to the full list, where swaps and the coordinator's editing live.
import { index as standbyIndex } from '@/routes/standby';
import { Link } from '@inertiajs/vue3';

defineProps({
    // [{ label?: string, names: [{ name: string|null, own: boolean }] }]
    days: { type: Array, required: true },
});
</script>

<template>
    <Link
        :href="standbyIndex().url"
        class="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-2xl bg-surface px-4 py-2.5 text-sm shadow-sm transition hover:bg-ink/5"
        data-testid="standby-strip"
    >
        <span class="font-semibold text-ink">{{ $t('standby.title') }}</span>
        <span v-for="(day, i) in days" :key="i" class="inline-flex items-baseline gap-1">
            <span v-if="day.label" class="text-xs font-medium text-ink/40">{{ day.label }}</span>
            <template v-for="(entry, j) in day.names" :key="j">
                <span v-if="j > 0" class="text-ink/30">&amp;</span>
                <span
                    :class="
                        entry.own
                            ? 'rounded-full bg-hort-teal-dark px-2 font-semibold text-white'
                            : entry.name
                              ? 'text-ink'
                              : 'text-warn-dark'
                    "
                >
                    {{ entry.name ?? $t('standby.open') }}
                </span>
            </template>
        </span>
    </Link>
</template>
