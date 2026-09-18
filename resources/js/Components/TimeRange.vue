<script setup>
import TimeSelect from './TimeSelect.vue';

const start = defineModel('start', { type: String, default: '' });
const end = defineModel('end', { type: String, default: '' });

const props = defineProps({
    // Earliest selectable hour — the Hort's pickups start at 11:00, but an Aktivität
    // (Waldtag, Schwimmen) can run in the morning.
    from: { type: String, default: '11:00' },
    // Optional test hook: gives the two halves `<testId>-start` / `-end`.
    testId: { type: String, default: null },
});

// When the user edits the start, jump the end to one hour later.
function onStartChange(value) {
    if (!value) {
        return;
    }
    const [h, m] = value.split(':').map(Number);
    const nextHour = (h + 1) % 24;
    end.value = `${String(nextHour).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}
</script>

<template>
    <!-- Each half may shrink (`min-w-0`), or the four dropdowns push past the column
         they sit in — the Programm row gives them 18rem, not whatever they'd like. -->
    <div class="flex items-center gap-2">
        <TimeSelect
            v-model="start"
            :from="props.from"
            :test-id="props.testId ? `${props.testId}-start` : null"
            class="min-w-0 flex-1"
            @change="onStartChange"
        />
        <span class="shrink-0 text-ink/40">–</span>
        <TimeSelect
            v-model="end"
            :from="props.from"
            :test-id="props.testId ? `${props.testId}-end` : null"
            class="min-w-0 flex-1"
        />
    </div>
</template>
