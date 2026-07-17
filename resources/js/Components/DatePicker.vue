<script setup>
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

// A hand-rolled month-calendar date picker (no external library) shared by the
// Heute board's day nav and the form date fields. Fully controlled: the label/
// trigger opens it, clicking a day emits the ISO date, clicking the backdrop closes
// it without change. Month + year dropdowns make far dates (birthdays) quick.
const props = defineProps({
    modelValue: { type: String, default: null }, // ISO 'YYYY-MM-DD' or null
    min: { type: String, default: null },
    max: { type: String, default: null },
    disableWeekends: { type: Boolean, default: false }, // Hort days only (board)
    today: { type: String, default: null }, // ISO; default = the browser's today
    placeholder: { type: String, default: '' },
    id: { type: String, default: undefined },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false }, // allow unsetting a nullable date
});

const emit = defineEmits(['update:modelValue']);

const WEEKDAY_INITIALS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
const locale = computed(() => usePage().props?.locale || 'de');

function parse(iso) {
    return new Date(`${iso}T00:00:00`);
}

function toIso(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

const todayIso = computed(() => props.today || toIso(new Date()));

const open = ref(false);
const view = ref(parse(props.modelValue || todayIso.value)); // the displayed month

function openPicker() {
    if (props.disabled) {
        return;
    }
    view.value = parse(props.modelValue || todayIso.value);
    open.value = true;
}

const display = computed(() =>
    props.modelValue
        ? new Intl.DateTimeFormat(locale.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(parse(props.modelValue))
        : '',
);

const months = computed(() =>
    Array.from({ length: 12 }, (_, m) => ({
        value: m,
        label: new Intl.DateTimeFormat(locale.value, { month: 'long' }).format(new Date(2000, m, 1)),
    })),
);

// Year options (recent first), bounded by min/max when given.
const years = computed(() => {
    const nowY = new Date().getFullYear();
    const start = props.min ? parse(props.min).getFullYear() : nowY - 100;
    const end = props.max ? parse(props.max).getFullYear() : nowY + 10;
    const list = [];
    for (let y = end; y >= start; y--) {
        list.push(y);
    }
    return list;
});

const cells = computed(() => {
    const y = view.value.getFullYear();
    const m = view.value.getMonth();
    const startOffset = (new Date(y, m, 1).getDay() + 6) % 7; // Monday-start
    const daysInMonth = new Date(y, m + 1, 0).getDate();

    const out = [];
    for (let i = 0; i < startOffset; i++) {
        out.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(y, m, d);
        const id = toIso(date);
        const weekend = date.getDay() === 0 || date.getDay() === 6;
        out.push({
            id,
            day: d,
            disabled: (props.disableWeekends && weekend)
                || (props.min && id < props.min)
                || (props.max && id > props.max),
            isToday: id === todayIso.value,
            isSelected: id === props.modelValue,
        });
    }
    return out;
});

function shiftMonth(delta) {
    view.value = new Date(view.value.getFullYear(), view.value.getMonth() + delta, 1);
}

function setMonth(event) {
    view.value = new Date(view.value.getFullYear(), Number(event.target.value), 1);
}

function setYear(event) {
    view.value = new Date(Number(event.target.value), view.value.getMonth(), 1);
}

function select(cell) {
    if (cell.disabled) {
        return;
    }
    open.value = false;
    emit('update:modelValue', cell.id);
}

function clear() {
    open.value = false;
    emit('update:modelValue', null);
}

// Escape closes the popover without changing the value.
function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}
watch(open, (isOpen) => {
    if (isOpen) {
        window.addEventListener('keydown', onKeydown);
    } else {
        window.removeEventListener('keydown', onKeydown);
    }
});
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="relative">
        <!-- Trigger: a custom one via the slot (e.g. the board's date label), else an input-style button. -->
        <slot name="trigger" :open="openPicker" :display="display">
            <button
                :id="id"
                type="button"
                :disabled="disabled"
                class="flex w-full items-center justify-between gap-2 rounded-md border border-ink/20 bg-surface px-3 py-2 text-left text-sm shadow-sm transition focus:border-hort-teal focus:outline-none focus:ring-1 focus:ring-hort-teal disabled:opacity-50"
                @click="openPicker"
            >
                <span :class="display ? 'text-ink' : 'text-ink/40'">{{ display || placeholder }}</span>
                <CalendarDaysIcon class="h-5 w-5 shrink-0 text-ink/40" />
            </button>
        </slot>

        <div v-if="open">
            <div class="fixed inset-0 z-30" @click="open = false" />
            <div
                data-testid="date-picker"
                class="absolute left-1/2 top-full z-40 mt-1 w-64 -translate-x-1/2 rounded-2xl bg-surface p-3 text-left shadow-lg ring-1 ring-ink/10"
            >
                <div class="mb-2 flex items-center gap-1">
                    <button type="button" class="rounded-lg p-1 text-ink/60 hover:bg-ink/5" :aria-label="$t('components.day.prev')" @click="shiftMonth(-1)">
                        <ChevronLeftIcon class="h-4 w-4" />
                    </button>
                    <select
                        :value="view.getMonth()"
                        class="min-w-0 flex-1 rounded-md border-ink/20 py-1 text-sm focus:border-hort-teal focus:ring-hort-teal"
                        @change="setMonth"
                    >
                        <option v-for="mo in months" :key="mo.value" :value="mo.value">{{ mo.label }}</option>
                    </select>
                    <select
                        :value="view.getFullYear()"
                        class="rounded-md border-ink/20 py-1 text-sm focus:border-hort-teal focus:ring-hort-teal"
                        @change="setYear"
                    >
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                    <button type="button" class="rounded-lg p-1 text-ink/60 hover:bg-ink/5" :aria-label="$t('components.day.next')" @click="shiftMonth(1)">
                        <ChevronRightIcon class="h-4 w-4" />
                    </button>
                </div>
                <div class="grid grid-cols-7 gap-0.5 text-center text-[10px] font-medium text-ink/40">
                    <span v-for="w in WEEKDAY_INITIALS" :key="w">{{ w }}</span>
                </div>
                <div class="mt-1 grid grid-cols-7 gap-0.5">
                    <template v-for="(cell, i) in cells" :key="i">
                        <span v-if="!cell" />
                        <button
                            v-else
                            type="button"
                            :disabled="cell.disabled"
                            :data-testid="cell.disabled ? undefined : `date-pick-${cell.id}`"
                            class="rounded-lg py-1.5 text-xs transition"
                            :class="[
                                cell.isSelected
                                    ? 'bg-hort-teal font-bold text-hort-navy'
                                    : cell.isToday
                                        ? 'font-bold text-hort-teal-dark'
                                        : 'text-ink hover:bg-hort-teal/10',
                                cell.disabled ? 'cursor-not-allowed text-ink/25 opacity-60 hover:bg-transparent' : '',
                            ]"
                            @click="select(cell)"
                        >
                            {{ cell.day }}
                        </button>
                    </template>
                </div>
                <div v-if="clearable && modelValue" class="mt-2 border-t border-ink/10 pt-2 text-center">
                    <button
                        type="button"
                        data-testid="date-clear"
                        class="text-xs font-medium text-ink/50 underline-offset-2 transition hover:text-ink hover:underline"
                        @click="clear"
                    >
                        {{ $t('components.day.clear') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
