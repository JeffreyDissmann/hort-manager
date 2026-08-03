<script setup>
import DatePicker from '@/Components/DatePicker.vue';
import { ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
    day: { type: Object, required: true },
});

const emit = defineEmits(['navigate']);

// Relative label from the (calendar-day) offset: 0 = today, +1 tomorrow, -1 yesterday …
const relative = computed(() => {
    const o = props.day.offset ?? 0;
    if (o === 0) return t('components.day.today');
    if (o === 1) return t('components.day.tomorrow');
    if (o === -1) return t('components.day.yesterday');
    return o > 0
        ? t('components.day.in_days', { n: o })
        : t('components.day.days_ago', { n: -o });
});

// Strong colour cue: teal = today, amber = a future day, grey = a past day.
const tone = computed(() => {
    const o = props.day.offset ?? 0;
    if (o === 0) return 'bg-hort-teal text-hort-navy';
    return o > 0 ? 'bg-amber-100 text-amber-700' : 'bg-ink/10 text-ink/50';
});

// The server's "today" (selected day − offset) — accurate regardless of browser timezone.
const todayIso = computed(() => {
    const d = new Date(`${props.day.iso}T00:00:00`);
    d.setDate(d.getDate() - (props.day.offset ?? 0));
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});
</script>

<template>
    <div class="flex items-center justify-between gap-2">
        <button
            type="button"
            data-testid="day-prev"
            class="rounded-lg p-2 text-ink/60 transition hover:bg-ink/5 active:scale-95 disabled:opacity-30 disabled:hover:bg-transparent"
            :disabled="!day.prev"
            :aria-label="$t('components.day.prev')"
            @click="day.prev && emit('navigate', day.prev)"
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

            <!-- The date label opens the shared date picker (Hort weekdays only). -->
            <DatePicker
                :model-value="day.iso"
                :min="day.floor"
                :today="todayIso"
                disable-weekends
                @update:model-value="(iso) => emit('navigate', iso)"
            >
                <template #trigger="{ open }">
                    <button
                        type="button"
                        data-testid="day-picker-toggle"
                        class="mt-1 flex w-full items-center justify-center gap-1 text-xs font-medium text-ink/60 underline-offset-2 hover:underline"
                        @click="open"
                    >
                        {{ day.label }}
                        <ChevronDownIcon class="h-3 w-3" />
                    </button>
                </template>
            </DatePicker>

            <button
                v-if="!day.is_today"
                type="button"
                data-testid="day-today"
                class="mt-0.5 block w-full text-xs font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                @click="emit('navigate', null)"
            >
                {{ $t('components.day.go_current') }}
            </button>
        </div>

        <button
            type="button"
            data-testid="day-next"
            class="rounded-lg p-2 text-ink/60 transition hover:bg-ink/5 active:scale-95"
            :aria-label="$t('components.day.next')"
            @click="emit('navigate', day.next)"
        >
            <ChevronRightIcon class="h-5 w-5" />
        </button>
    </div>
</template>
