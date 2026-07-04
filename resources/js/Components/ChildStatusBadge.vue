<script setup>
// A child's excursion status as a coloured chip: "Name: joining / not joining / …".
import { t } from '@/i18n';
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    // true = joining, false = not joining, null = not answered.
    response: { type: Boolean, default: null },
    // Overrides the "not answered" label — e.g. "keine Rückmeldung" for a past
    // excursion. Null → the default "still open" label.
    pendingLabel: { type: String, default: null },
});

const label = computed(() => {
    if (props.response === true) {
        return t('components.child_status.present');
    }
    if (props.response === false) {
        return t('components.child_status.absent');
    }
    return props.pendingLabel ?? t('components.child_status.pending');
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
