<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-[100dvh] flex-col items-center bg-hort-sand px-6 pt-10 sm:justify-center sm:pt-0"
    >
        <Link href="/" class="flex flex-col items-center gap-2">
            <ApplicationLogo class="h-16 w-16" />
            <span class="font-display text-3xl text-hort-navy">Hort-Manager</span>
        </Link>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-6 shadow-sm sm:max-w-md sm:rounded-2xl"
        >
            <slot />
        </div>
    </div>
</template>
