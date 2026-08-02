<script setup>
import { program as programRoute } from '@/routes';
import {
    update as programUpdate,
    defaults as programDefaults,
    settings as programSettings,
    digestTime as programDigestTime,
} from '@/routes/program';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TimeRange from '@/Components/TimeRange.vue';
import TimeSelect from '@/Components/TimeSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import WeekNav from '@/Components/WeekNav.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    week: { type: Object, default: () => ({}) },
    days: { type: Array, default: () => [] },
    homeworkDefaults: { type: Array, default: () => [] },
    lateChangeCutoff: { type: String, default: '12:00' },
    weeklyDigestTime: { type: String, default: '12:00' },
    // Always the digest time minus the fixed lead — displayed, not editable.
    programReminderTime: { type: String, default: '11:30' },
    programReminderLeadMinutes: { type: Number, default: 30 },
});

const flash = computed(() => usePage().props.flash?.status);
const saving = ref(false);
const savingDefaults = ref(false);
const savingSettings = ref(false);
const savingDigestTime = ref(false);

// `no_homework` drives the "Keine Hausaufgaben" checkbox — on when there's no
// effective homework for the day (explicit none, or no default/override at all).
const withHomeworkFlag = (d) => ({ ...d, no_homework: !d.homework_start });
const days = ref(props.days.map(withHomeworkFlag));
watch(
    () => props.days,
    (value) => {
        days.value = value.map(withHomeworkFlag);
    },
);

function toggleNoHomework(day) {
    if (day.no_homework) {
        day.homework_start = null;
        day.homework_end = null;
    } else if (!day.homework_start && day.default_start) {
        // Unchecked with nothing set → restore the weekday default to edit from.
        day.homework_start = day.default_start;
        day.homework_end = day.default_end;
    }
}

const withDefaultFlag = (d) => ({ ...d, no_homework: !d.start });
const defaults = ref(props.homeworkDefaults.map(withDefaultFlag));
watch(
    () => props.homeworkDefaults,
    (value) => {
        defaults.value = value.map(withDefaultFlag);
    },
);

function toggleDefaultNoHomework(d) {
    if (d.no_homework) {
        d.start = null;
        d.end = null;
    }
}

function save() {
    saving.value = true;
    router.patch(
        programUpdate().url,
        {
            days: days.value.map((d) => ({
                date: d.date,
                lunch: d.lunch || null,
                activity: d.activity || null,
                homework_start: d.no_homework ? null : d.homework_start || null,
                homework_end: d.no_homework ? null : d.homework_end || null,
                homework_none: d.no_homework,
                // Ferienbetreuung days save their Betreuungszeit with the week.
                care_starts_at: d.care?.starts_at ?? null,
                care_ends_at: d.care?.ends_at ?? null,
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
        programDefaults().url,
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

const lateChangeCutoff = ref(props.lateChangeCutoff);
watch(
    () => props.lateChangeCutoff,
    (value) => {
        lateChangeCutoff.value = value;
    },
);

// Deep link from the notification settings (/program#late-change): scroll the card
// into view and flash a ring so it's obvious which setting was meant.
const lateChangeCard = ref(null);
const highlightLateChange = ref(false);

onMounted(() => {
    if (window.location.hash !== '#late-change') {
        return;
    }
    lateChangeCard.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlightLateChange.value = true;
    setTimeout(() => (highlightLateChange.value = false), 2000);
});

function saveSettings() {
    savingSettings.value = true;
    router.patch(
        programSettings().url,
        { late_change_cutoff: lateChangeCutoff.value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (savingSettings.value = false),
        },
    );
}

const weeklyDigestTime = ref(props.weeklyDigestTime);
watch(
    () => props.weeklyDigestTime,
    (value) => {
        weeklyDigestTime.value = value;
    },
);

function saveDigestTime() {
    savingDigestTime.value = true;
    router.patch(
        programDigestTime().url,
        { weekly_digest_time: weeklyDigestTime.value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (savingDigestTime.value = false),
        },
    );
}

function goWeek(date) {
    router.get(
        programRoute(date ? { query: { week: date } } : {}).url,
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
    <Head :title="$t('program.title')" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('program.header') }}</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <p class="text-sm text-ink/60">
                {{ $t('program.intro') }}
            </p>

            <!-- Week navigation (kept compact so it doesn't spread on wide screens) -->
            <div
                class="mx-auto max-w-md"
                @touchstart="onTouchStart"
                @touchend="onTouchEnd"
            >
                <WeekNav :week="week" @navigate="goWeek" />
            </div>

            <!--
                One row per weekday. Stacked on mobile; on wide screens the
                fields line up horizontally (weekday · lunch · activity · homework).
            -->
            <div
                v-for="day in days"
                :key="day.date"
                :data-testid="`program-day-${day.date}`"
                class="rounded-2xl p-4 shadow-sm"
                :class="day.care ? 'bg-hort-teal/10 ring-1 ring-hort-teal/40' : 'bg-surface'"
            >
                <div class="lg:grid lg:grid-cols-[7rem,minmax(0,18rem),minmax(0,18rem),auto] lg:items-start lg:gap-5">
                    <div>
                        <p class="font-semibold" :class="day.closed ? 'text-ink/40' : 'text-ink'">
                            {{ day.label }}
                            <span class="font-normal text-ink/40">
                                · {{ day.date_label }}
                            </span>
                        </p>
                        <!-- A care day looks different on purpose: it isn't a normal
                             Hort day, and the Betreuungszeit below isn't a normal field. -->
                        <p
                            v-if="day.care"
                            :data-testid="`program-care-${day.date}`"
                            class="mt-1 inline-block rounded-lg bg-hort-teal/25 px-2 py-0.5 text-xs font-semibold text-hort-teal-dark"
                        >
                            {{ $t('enums.holiday_period_type.care') }}
                        </p>
                        <p v-if="day.care" class="mt-0.5 text-xs text-ink/50">{{ day.care.name }}</p>
                        <p
                            v-if="day.birthdays && day.birthdays.length"
                            class="mt-1 rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                        >
                            🎂
                            <span v-for="(b, j) in day.birthdays" :key="b.name">
                                <template v-if="j > 0">, </template>{{ b.name }}
                                ({{ $t('program.birthday_turns', { age: b.turns }) }})
                            </span>
                        </p>
                    </div>

                    <!-- Schließzeit: nothing to plan, so the three fields make way for
                         the closure's name. -->
                    <p
                        v-if="day.closed"
                        :data-testid="`program-closed-${day.date}`"
                        class="mt-3 text-sm text-ink/50 lg:col-span-3 lg:mt-0"
                    >
                        🚫 {{ day.closed }}
                    </p>

                    <template v-else>
                    <div class="mt-3 lg:mt-0">
                        <InputLabel :for="`lunch-${day.date}`" :value="$t('program.lunch')" />
                        <TextInput
                            :id="`lunch-${day.date}`"
                            v-model="day.lunch"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full"
                            :placeholder="$t('program.lunch_placeholder')"
                        />
                    </div>

                    <div class="mt-3 lg:mt-0">
                        <InputLabel :for="`activity-${day.date}`" :value="$t('program.activity')" />
                        <TextInput
                            :id="`activity-${day.date}`"
                            v-model="day.activity"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full"
                            :placeholder="$t('program.activity_placeholder')"
                        />
                    </div>

                    <!-- Ferienbetreuung takes the homework slot: there is no school to
                         bring any home from, but there is a Betreuungszeit to set. -->
                    <div v-if="day.care" class="mt-3 lg:mt-0">
                        <InputLabel :value="$t('program.care_window')" />
                        <!-- Same layout as the homework TimeRange (each half flexes, so
                             the hour/minute dropdowns never get clipped) — but without
                             its „end = start + 1h" nudge, which would wreck a
                             Betreuungszeit every time the start is adjusted. -->
                        <div class="mt-1 flex items-center gap-2">
                            <TimeSelect
                                v-model="day.care.starts_at"
                                from="06:00"
                                :test-id="`care-start-${day.date}`"
                                class="min-w-0 flex-1"
                            />
                            <span class="shrink-0 text-ink/40">–</span>
                            <TimeSelect
                                v-model="day.care.ends_at"
                                from="06:00"
                                :test-id="`care-end-${day.date}`"
                                class="min-w-0 flex-1"
                            />
                        </div>
                    </div>

                    <div v-else class="mt-3 lg:mt-0">
                        <InputLabel :value="$t('program.homework')" />
                        <label class="mt-1 flex items-center gap-2 text-sm text-ink/70">
                            <Checkbox
                                :checked="day.no_homework"
                                @update:checked="
                                    (v) => {
                                        day.no_homework = v;
                                        toggleNoHomework(day);
                                    }
                                "
                            />
                            {{ $t('program.no_homework') }}
                        </label>
                        <TimeRange
                            v-if="!day.no_homework"
                            v-model:start="day.homework_start"
                            v-model:end="day.homework_end"
                            class="mt-2"
                        />
                    </div>
                    </template>
                </div>
            </div>

            <div class="flex justify-end">
                <PrimaryButton :disabled="saving" @click="save">
                    {{ $t('program.save_week') }}
                </PrimaryButton>
            </div>

            <!-- Default homework schedule (Mo–Fr) -->
            <div class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="font-semibold text-ink">
                    {{ $t('program.default_homework_heading') }}
                </p>
                <p class="mb-3 mt-1 text-sm text-ink/60">
                    {{ $t('program.default_homework_intro') }}
                </p>
                <div class="space-y-2 lg:max-w-2xl">
                    <div
                        v-for="d in defaults"
                        :key="d.weekday"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span class="w-8 shrink-0 text-sm font-medium text-ink/60">
                            {{ d.label }}
                        </span>
                        <label class="flex shrink-0 items-center gap-2 text-sm text-ink/70">
                            <Checkbox
                                :checked="d.no_homework"
                                @update:checked="
                                    (v) => {
                                        d.no_homework = v;
                                        toggleDefaultNoHomework(d);
                                    }
                                "
                            />
                            {{ $t('common.none') }}
                        </label>
                        <TimeRange
                            v-if="!d.no_homework"
                            v-model:start="d.start"
                            v-model:end="d.end"
                            class="flex-1"
                        />
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <PrimaryButton :disabled="savingDefaults" @click="saveDefaults">
                        {{ $t('program.save_default') }}
                    </PrimaryButton>
                </div>
            </div>

            <!-- Hort-wide cutoff for late same-day changes -->
            <div
                id="late-change"
                ref="lateChangeCard"
                class="rounded-2xl bg-surface p-4 shadow-sm transition"
                :class="highlightLateChange ? 'ring-2 ring-hort-teal' : ''"
            >
                <p class="font-semibold text-ink">
                    {{ $t('program.late_change_heading') }}
                </p>
                <p class="mb-3 mt-1 text-sm text-ink/60">
                    {{ $t('program.late_change_intro') }}
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <InputLabel
                        for="late-change-cutoff"
                        :value="$t('program.late_change_label')"
                        class="shrink-0"
                    />
                    <TimeSelect
                        id="late-change-cutoff"
                        v-model="lateChangeCutoff"
                        from="08:00"
                        test-id="late-change-cutoff"
                        class="w-40"
                    />
                </div>
                <div class="mt-3 flex justify-end">
                    <PrimaryButton :disabled="savingSettings" @click="saveSettings">
                        {{ $t('program.save_late_change') }}
                    </PrimaryButton>
                </div>
            </div>

            <!-- When the Monday digest goes to parents (staff are nudged an hour earlier) -->
            <div id="weekly-digest" class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="font-semibold text-ink">
                    {{ $t('program.digest_heading') }}
                </p>
                <p class="mb-3 mt-1 text-sm text-ink/60">
                    {{
                        $t('program.digest_intro', {
                            time: programReminderTime,
                            minutes: programReminderLeadMinutes,
                        })
                    }}
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <InputLabel
                        for="weekly-digest-time"
                        :value="$t('program.digest_label')"
                        class="shrink-0"
                    />
                    <TimeSelect
                        id="weekly-digest-time"
                        v-model="weeklyDigestTime"
                        from="08:00"
                        test-id="weekly-digest-time"
                        class="w-40"
                    />
                </div>
                <div class="mt-3 flex justify-end">
                    <PrimaryButton :disabled="savingDigestTime" @click="saveDigestTime">
                        {{ $t('program.save_digest_time') }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
