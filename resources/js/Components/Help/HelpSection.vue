<script setup>
// One titled block of a help chapter. Keeps every chapter looking the same.
defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <section class="space-y-3">
        <h3 class="text-lg font-semibold text-ink">{{ title }}</h3>
        <slot />
    </section>
</template>
