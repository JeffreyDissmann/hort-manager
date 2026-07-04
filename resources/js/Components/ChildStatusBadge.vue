<script setup>
// A child's excursion status as a coloured chip: "Name: dabei / nicht dabei / …".
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    // true = dabei, false = nicht dabei, null = not answered.
    response: { type: Boolean, default: null },
    // What to show when unanswered — "noch offen" while the poll runs, or e.g.
    // "keine Rückmeldung" for a past excursion.
    pendingLabel: { type: String, default: 'noch offen' },
});

const label = computed(() => {
    if (props.response === true) {
        return 'dabei';
    }
    return props.response === false ? 'nicht dabei' : props.pendingLabel;
});

const badgeClass = computed(() => {
    if (props.response === true) {
        return 'bg-hort-teal/15 text-hort-teal-dark';
    }
    return props.response === false
        ? 'bg-hort-purple/10 text-hort-purple'
        : 'bg-hort-navy/5 text-hort-navy/40';
});
</script>

<template>
    <span class="rounded-md px-2 py-0.5 text-xs font-medium" :class="badgeClass">
        {{ name }}: {{ label }}
    </span>
</template>
