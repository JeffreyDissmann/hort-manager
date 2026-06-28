<script setup>
import { usePage } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';

// Mirror the number of unanswered Ausflug polls onto the installed app's icon badge.
const page = usePage();

function syncBadge(count) {
    if (!('setAppBadge' in navigator)) {
        return;
    }
    if (count > 0) {
        navigator.setAppBadge(count).catch(() => {});
    } else {
        navigator.clearAppBadge().catch(() => {});
    }
}

onMounted(() => syncBadge(page.props.pendingPolls || 0));
watch(
    () => page.props.pendingPolls,
    (count) => syncBadge(count || 0),
);
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
