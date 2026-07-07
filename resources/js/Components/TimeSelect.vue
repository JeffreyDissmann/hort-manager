<script setup>
import { computed, ref, watch } from 'vue';

const model = defineModel({ type: String, default: '' });
const emit = defineEmits(['change']);

const props = defineProps({
    from: { type: String, default: '06:00' },
    to: { type: String, default: '18:00' },
    step: { type: Number, default: 15 },
});

const hour = ref('');
const minute = ref('');

function sync(value) {
    hour.value = value ? value.slice(0, 2) : '';
    minute.value = value ? value.slice(3, 5) : '';
}

sync(model.value);
watch(model, sync);

const hours = computed(() => {
    const list = [];
    for (let h = parseInt(props.from.slice(0, 2), 10); h <= parseInt(props.to.slice(0, 2), 10); h++) {
        list.push(String(h).padStart(2, '0'));
    }
    return list;
});

const minutes = computed(() => {
    const list = [];
    for (let m = 0; m < 60; m += props.step) {
        list.push(String(m).padStart(2, '0'));
    }
    return list;
});

// Fired only on user interaction (not on programmatic model changes).
function update() {
    if (!hour.value) {
        model.value = '';
        emit('change', '');
        return;
    }
    if (!minute.value) {
        minute.value = '00';
    }
    model.value = `${hour.value}:${minute.value}`;
    emit('change', model.value);
}

const selectClass =
    'min-w-0 flex-1 rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal';
</script>

<template>
    <div class="flex items-center gap-1">
        <select v-model="hour" :class="selectClass" @change="update">
            <option value="">––</option>
            <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
        </select>
        <span class="font-semibold text-ink/40">:</span>
        <select v-model="minute" :class="selectClass" @change="update">
            <option value="">––</option>
            <option v-for="m in minutes" :key="m" :value="m">{{ m }}</option>
        </select>
    </div>
</template>
