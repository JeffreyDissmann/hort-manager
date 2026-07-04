<script setup>
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    week: { type: Object, required: true },
});

const emit = defineEmits(['navigate']);
</script>

<template>
    <div class="flex items-center justify-between gap-2">
        <button
            type="button"
            class="rounded-lg p-2 text-hort-navy/60 transition hover:bg-hort-navy/5 active:scale-95"
            :aria-label="$t('components.week.prev')"
            @click="emit('navigate', week.prev)"
        >
            <ChevronLeftIcon class="h-5 w-5" />
        </button>

        <div class="text-center">
            <p class="text-sm font-semibold text-hort-navy">
                {{ week.is_current ? $t('components.week.current') : $t('components.week.week') }}
            </p>
            <p class="text-xs text-hort-navy/50">{{ week.label }}</p>
            <button
                v-if="!week.is_current"
                type="button"
                class="mt-0.5 text-xs font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                @click="emit('navigate', null)"
            >
                {{ $t('components.week.go_current') }}
            </button>
        </div>

        <button
            type="button"
            class="rounded-lg p-2 text-hort-navy/60 transition hover:bg-hort-navy/5 active:scale-95"
            :aria-label="$t('components.week.next')"
            @click="emit('navigate', week.next)"
        >
            <ChevronRightIcon class="h-5 w-5" />
        </button>
    </div>
</template>
