<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

// Numbered pagination for a Laravel paginator (expects `links`, `from`, `to`,
// `total`, `last_page`). First/last links render as chevrons, „…" gaps are shown
// disabled, and the current page is highlighted.
const props = defineProps({
    paginator: { type: Object, required: true },
});

const isFirst = (i) => i === 0;
const isLast = (i) => i === props.paginator.links.length - 1;
</script>

<template>
    <nav v-if="paginator.last_page > 1" class="flex items-center justify-between gap-4 text-sm">
        <span class="shrink-0 text-ink/40">{{ paginator.from }}–{{ paginator.to }} / {{ paginator.total }}</span>

        <div class="flex flex-wrap items-center justify-end gap-1">
            <template v-for="(link, i) in paginator.links" :key="i">
                <!-- Previous -->
                <Link
                    v-if="isFirst(i) && link.url"
                    :href="link.url"
                    preserve-scroll
                    class="rounded-md p-2 text-ink/60 transition hover:bg-ink/5"
                    :aria-label="$t('activity.newer')"
                >
                    <ChevronLeftIcon class="h-4 w-4" />
                </Link>
                <span v-else-if="isFirst(i)" class="rounded-md p-2 text-ink/20">
                    <ChevronLeftIcon class="h-4 w-4" />
                </span>

                <!-- Next -->
                <Link
                    v-else-if="isLast(i) && link.url"
                    :href="link.url"
                    preserve-scroll
                    class="rounded-md p-2 text-ink/60 transition hover:bg-ink/5"
                    :aria-label="$t('activity.older')"
                >
                    <ChevronRightIcon class="h-4 w-4" />
                </Link>
                <span v-else-if="isLast(i)" class="rounded-md p-2 text-ink/20">
                    <ChevronRightIcon class="h-4 w-4" />
                </span>

                <!-- Gap -->
                <span v-else-if="!link.url" class="px-1.5 text-ink/30">…</span>

                <!-- Page number -->
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="min-w-[2rem] rounded-md px-2.5 py-1.5 text-center tabular-nums transition"
                    :class="link.active ? 'bg-hort-teal/20 font-semibold text-ink' : 'text-ink/60 hover:bg-ink/5'"
                >
                    {{ link.label }}
                </Link>
            </template>
        </div>
    </nav>
</template>
