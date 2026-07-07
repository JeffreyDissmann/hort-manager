<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-ink/80 transition duration-150 ease-in-out hover:bg-ink/5 focus:bg-ink/5 focus:outline-none"
    >
        <slot />
    </Link>
</template>
