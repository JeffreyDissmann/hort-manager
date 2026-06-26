<script setup>
import TimeSelect from './TimeSelect.vue';

const start = defineModel('start', { type: String, default: '' });
const end = defineModel('end', { type: String, default: '' });

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
    <div class="flex items-center gap-2">
        <TimeSelect v-model="start" class="flex-1" @change="onStartChange" />
        <span class="text-hort-navy/40">–</span>
        <TimeSelect v-model="end" class="flex-1" />
    </div>
</template>
