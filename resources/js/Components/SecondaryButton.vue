<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-ink/20 bg-surface px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink/80 shadow-sm transition duration-150 ease-in-out hover:bg-ink/5 focus:outline-none focus:ring-2 focus:ring-hort-teal focus:ring-offset-2 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
