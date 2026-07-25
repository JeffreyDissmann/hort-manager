<script setup>
// Segmented control choosing a booking's intent: a normal „Buchung" (income/expense,
// incl. refunds) or an „Umbuchung" (internal transfer). Shared by review + edit so
// the two windows switch intent identically.
const mode = defineModel({ type: String, default: 'booking' });

const options = [
    { value: 'booking', key: 'accounting.review.mode_booking' },
    { value: 'transfer', key: 'accounting.review.mode_transfer' },
];
</script>

<template>
    <div class="flex gap-0.5 rounded-lg bg-ink/5 p-0.5">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition"
            :class="mode === o.value ? 'bg-surface text-ink shadow-sm' : 'text-ink/50 hover:text-ink'"
            :data-testid="'mode-' + o.value"
            @click="mode = o.value"
        >
            {{ $t(o.key) }}
        </button>
    </div>
</template>
