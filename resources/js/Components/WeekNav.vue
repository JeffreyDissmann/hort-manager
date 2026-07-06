<script setup>
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
    week: { type: Object, required: true },
});

const emit = defineEmits(['navigate']);

// Relative label from the week offset (0 = current, +1 next, -2 two weeks ago …).
const relative = computed(() => {
    const o = props.week.offset ?? 0;
    if (o === 0) return t('components.week.current');
    if (o === 1) return t('components.week.next');
    if (o === -1) return t('components.week.prev');
    return o > 0
        ? t('components.week.in_weeks', { n: o })
        : t('components.week.weeks_ago', { n: -o });
});

// Strong colour cue: teal = now, amber = a future week, grey = a past week.
const tone = computed(() => {
    const o = props.week.offset ?? 0;
    if (o === 0) return 'bg-hort-teal text-hort-navy';
    return o > 0 ? 'bg-amber-100 text-amber-700' : 'bg-hort-navy/10 text-hort-navy/50';
});
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
            <span
                class="inline-block rounded-full px-3 py-1 text-sm font-bold"
                :class="tone"
            >
                {{ relative }}
            </span>
            <p class="mt-1 text-xs font-medium text-hort-navy/60">{{ week.label }}</p>
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
