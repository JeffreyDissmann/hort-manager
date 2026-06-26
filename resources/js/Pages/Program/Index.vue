<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TimeRange from '@/Components/TimeRange.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    week: { type: Object, default: () => ({}) },
    days: { type: Array, default: () => [] },
    homeworkDefaults: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash?.status);
const saving = ref(false);
const savingDefaults = ref(false);

const days = ref(props.days.map((d) => ({ ...d })));
watch(
    () => props.days,
    (value) => {
        days.value = value.map((d) => ({ ...d }));
    },
);

const defaults = ref(props.homeworkDefaults.map((d) => ({ ...d })));
watch(
    () => props.homeworkDefaults,
    (value) => {
        defaults.value = value.map((d) => ({ ...d }));
    },
);

function save() {
    saving.value = true;
    router.patch(
        route('program.update'),
        {
            days: days.value.map((d) => ({
                date: d.date,
                lunch: d.lunch || null,
                activity: d.activity || null,
                homework_start: d.homework_start || null,
                homework_end: d.homework_end || null,
            })),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (saving.value = false),
        },
    );
}

function saveDefaults() {
    savingDefaults.value = true;
    router.patch(
        route('program.defaults'),
        {
            defaults: defaults.value.map((d) => ({
                weekday: d.weekday,
                start: d.start || null,
                end: d.end || null,
            })),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (savingDefaults.value = false),
        },
    );
}

function goWeek(date) {
    router.get(
        route('program', date ? { week: date } : {}),
        {},
        { preserveScroll: true },
    );
}

let touchStartX = 0;
function onTouchStart(e) {
    touchStartX = e.changedTouches[0].clientX;
}
function onTouchEnd(e) {
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 60) {
        goWeek(dx < 0 ? props.week.next : props.week.prev);
    }
}
</script>

<template>
    <Head title="Programm" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Tagesprogramm</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <p class="text-sm text-hort-navy/60">
                Trage für jeden Tag das Mittagessen, die Aktivität und die
                Hausaufgabenzeit ein. Eltern sehen das auf „Heute" und im
                Abholplan.
            </p>

            <!-- Week navigation -->
            <div
                class="flex items-center justify-between gap-2"
                @touchstart="onTouchStart"
                @touchend="onTouchEnd"
            >
                <button
                    type="button"
                    class="rounded-lg p-2 text-hort-navy/60 transition hover:bg-hort-navy/5 active:scale-95"
                    aria-label="Vorige Woche"
                    @click="goWeek(week.prev)"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <div class="text-center">
                    <p class="text-sm font-semibold text-hort-navy">
                        {{ week.is_current ? 'Diese Woche' : 'Woche' }}
                    </p>
                    <p class="text-xs text-hort-navy/50">{{ week.label }}</p>
                    <button
                        v-if="!week.is_current"
                        type="button"
                        class="mt-0.5 text-xs font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                        @click="goWeek(null)"
                    >
                        Zur aktuellen Woche
                    </button>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-hort-navy/60 transition hover:bg-hort-navy/5 active:scale-95"
                    aria-label="Nächste Woche"
                    @click="goWeek(week.next)"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>

            <div
                v-for="day in days"
                :key="day.date"
                class="rounded-2xl bg-white p-4 shadow-sm"
            >
                <p class="mb-2 font-semibold text-hort-navy">
                    {{ day.label }}
                    <span class="font-normal text-hort-navy/40">
                        · {{ day.date_label }}
                    </span>
                </p>
                <div class="space-y-3">
                    <div>
                        <InputLabel :for="`lunch-${day.date}`" value="Mittagessen" />
                        <TextInput
                            :id="`lunch-${day.date}`"
                            v-model="day.lunch"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full"
                            placeholder="z. B. Nudeln mit Tomatensoße"
                        />
                    </div>
                    <div>
                        <InputLabel :for="`activity-${day.date}`" value="Aktivität" />
                        <TextInput
                            :id="`activity-${day.date}`"
                            v-model="day.activity"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full"
                            placeholder="z. B. Basteln, Ausflug in den Park"
                        />
                    </div>
                    <div>
                        <InputLabel value="Hausaufgaben" />
                        <TimeRange
                            v-model:start="day.homework_start"
                            v-model:end="day.homework_end"
                            class="mt-1"
                        />
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <PrimaryButton :disabled="saving" @click="save">
                    Woche speichern
                </PrimaryButton>
            </div>

            <!-- Default homework schedule (Mo–Fr) -->
            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <p class="font-semibold text-hort-navy">
                    Standard-Hausaufgabenzeiten
                </p>
                <p class="mb-3 mt-1 text-sm text-hort-navy/60">
                    Gilt an jedem Tag, sofern oben für den Tag nichts anderes
                    eingetragen ist.
                </p>
                <div class="space-y-2">
                    <div
                        v-for="d in defaults"
                        :key="d.weekday"
                        class="flex items-center gap-2"
                    >
                        <span class="w-8 shrink-0 text-sm font-medium text-hort-navy/60">
                            {{ d.label }}
                        </span>
                        <TimeRange
                            v-model:start="d.start"
                            v-model:end="d.end"
                            class="flex-1"
                        />
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <PrimaryButton :disabled="savingDefaults" @click="saveDefaults">
                        Standard speichern
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
