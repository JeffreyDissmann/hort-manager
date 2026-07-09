<script setup>
import { weeklyPlan, standardPlan } from '@/routes';
import { adjust as weeklyPlanAdjust, reset as weeklyPlanReset } from '@/routes/weekly-plan';
import { store as absenceStore, destroy as absenceDestroy } from '@/routes/absences';
import { confirm as companionConfirm } from '@/routes/companion';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TimeSelect from '@/Components/TimeSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import WeekNav from '@/Components/WeekNav.vue';
import WeekTimetable from '@/Components/WeekTimetable.vue';
import CompanionNotes from '@/Components/CompanionNotes.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    week: { type: Object, default: () => ({}) },
    weekDays: { type: Array, default: () => [] },
    currentWeek: { type: Array, default: () => [] },
    activities: { type: Array, default: () => [] },
    program: { type: Array, default: () => [] },
    weekTimetable: { type: Array, default: () => [] },
    weekAbsences: { type: Array, default: () => [] },
    children: { type: Array, default: () => [] },
    companionNotes: { type: Array, default: () => [] },
    methodOptions: { type: Array, default: () => [] },
    qualifierOptions: { type: Array, default: () => [] },
});

// „Kind (Grund – Kommentar)" per day, for the not-coming summary under the grid.
function absenceLine(day) {
    return day
        .map((a) => (a.comment ? `${a.name} (${a.label} – ${a.comment})` : `${a.name} (${a.label})`))
        .join(', ');
}

// Short prefix per „geht allein" time qualifier (bis/um/ab), keyed by value.
const qualifierPrefix = computed(() =>
    Object.fromEntries(props.qualifierOptions.map((o) => [o.value, o.prefix])),
);

// The prefix to show before a sent_home time — only for the meaningful deviations
// (bis/ab); „genau um" is the default and stays implicit to keep the cell clean.
function timePrefix(method, qualifier) {
    if (method !== 'sent_home' || !qualifier || qualifier === 'at') {
        return '';
    }
    return qualifierPrefix.value[qualifier] ?? '';
}

// The picked week's column headers show the weekday + its date; today is flagged.
const weekColumns = computed(() =>
    props.weekDays.map((d) => ({ label: d.label, sublabel: d.date_label, is_today: d.is_today })),
);

function goWeek(date) {
    router.get(
        weeklyPlan(date ? { query: { week: date } } : {}).url,
        {},
        { preserveScroll: true },
    );
}

// Swipe left/right to move between weeks.
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

const flash = computed(() => usePage().props.flash?.status);
const isStaff = computed(() => usePage().props.auth?.user?.role === 'staff');

function toMinutes(time) {
    return parseInt(time.slice(0, 2), 10) * 60 + parseInt(time.slice(3, 5), 10);
}

// Pickup falls inside that day's homework slot.
function homeworkConflict(day, i) {
    const hw = props.program[i];
    if (!day.time || !hw || !hw.homework_start || !hw.homework_end) {
        return false;
    }
    const pickup = toMinutes(day.time);
    return pickup >= toMinutes(hw.homework_start) && pickup < toMinutes(hw.homework_end);
}

// Solid `ink` time; the method reads from the warm/cool tint, and the "goes home
// alone" case additionally gets a 🚶 icon.
function cellClass(day) {
    if (!day.time) {
        return 'bg-ink/5 text-ink/30';
    }
    return day.method === 'sent_home'
        ? 'bg-hort-orange/20 text-ink'
        : 'bg-hort-teal/20 text-ink';
}

// --- Inline editor (modal) ---
const editing = ref(null); // { childId, childName, date, label }
const form = reactive({ planned_time: '', planned_method: '', time_qualifier: 'at', companion_child_id: '', note: '', absence_reason: '' });
const saveError = ref(''); // server validation message shown in the modal on a failed save
const saving = ref(false); // guards the save button against a double-submit while in flight

// Surface the first server error (e.g. an invalid companion) so a failed save isn't silent.
function showFirstError(errors) {
    saveError.value = errors.companion_child_id || errors.planned_time || Object.values(errors)[0] || '';
}

// While a fresh absence is staged (Krank/Kommt nicht), the plan is disabled and the
// comment becomes mandatory — nothing is submitted until the user hits Speichern.
const stagingAbsence = computed(() => form.absence_reason !== '');

// „Geht mit … mit": pick any other child; the companion picker replaces the time.
const goingWithChild = computed(() => form.planned_method === 'with_child');
// Every other child, flagged with whether they can actually be a companion that day
// (i.e. they have an own pickup and aren't away / tagging along themselves — the same
// rule the server enforces). Unavailable ones are shown but disabled, so the parent
// sees why rather than only learning on a failed save.
const companionChoices = computed(() =>
    props.children
        .filter((c) => c.id !== editing.value?.childId)
        .map((c) => ({
            id: c.id,
            name: c.name,
            time: c.times?.[editing.value?.date] ?? '',
            available: !!c.times?.[editing.value?.date],
        })),
);
const selectedCompanion = computed(() =>
    companionChoices.value.find((c) => c.id === form.companion_child_id),
);
const selectedCompanionName = computed(() => selectedCompanion.value?.name ?? '');
// The companion's own pickup time on the edited day — shown in the mirror hint.
const selectedCompanionTime = computed(() => selectedCompanion.value?.time ?? '');
// A chosen companion who isn't being picked up that day can't be a companion.
const selectedCompanionUnavailable = computed(
    () => !!selectedCompanion.value && !selectedCompanion.value.available,
);

const canSave = computed(() => {
    if (stagingAbsence.value) {
        return !!form.note.trim();
    }
    if (goingWithChild.value) {
        // a valid, still-available companion must be chosen
        return !!form.companion_child_id && !selectedCompanionUnavailable.value;
    }
    return true;
});

function openCell(child, day, dayMeta) {
    if (!day.editable) {
        return;
    }
    editing.value = {
        childId: child.id,
        childName: child.name,
        date: day.date,
        label: `${dayMeta.label} ${dayMeta.date_label}`,
        absent: day.absent ?? null,
    };
    form.planned_time = day.time ?? '';
    form.planned_method = day.method ?? '';
    form.time_qualifier = day.qualifier ?? 'at';
    form.companion_child_id = day.companion?.id ?? '';
    form.note = day.note ?? '';
    form.absence_reason = '';
    saveError.value = '';
}

// Staff editing a pickup from the Ganze-Woche timeline (kid carries the day data).
function openFromTimeline(kid, column) {
    openCell(kid, kid, { label: column.label, date_label: column.sublabel });
}

function closeEditor() {
    editing.value = null;
}

function save() {
    if (saving.value || !canSave.value) {
        return;
    }
    saveError.value = '';
    const opts = {
        preserveScroll: true,
        onStart: () => { saving.value = true; },
        onFinish: () => { saving.value = false; },
        onSuccess: closeEditor,
        onError: showFirstError,
    };

    // Staged absence: report it (with the now-required comment) instead of a plan.
    if (stagingAbsence.value) {
        router.post(
            absenceStore().url,
            {
                child_id: editing.value.childId,
                from: editing.value.date,
                to: editing.value.date,
                reason: form.absence_reason,
                comment: form.note || null,
            },
            opts,
        );
        return;
    }

    router.patch(
        weeklyPlanAdjust().url,
        {
            child_id: editing.value.childId,
            date: editing.value.date,
            planned_time: form.planned_time || null,
            planned_method: form.planned_method || null,
            time_qualifier: form.planned_method === 'sent_home' ? form.time_qualifier || null : null,
            companion_child_id: goingWithChild.value ? form.companion_child_id || null : null,
            note: form.note || null,
        },
        opts,
    );
}

// Confirm/decline another child going home with one of ours (companion's guardian/staff).
function answerCompanion(id, confirmed) {
    router.patch(companionConfirm(id).url, { confirmed }, { preserveScroll: true });
}

function resetDay() {
    router.patch(
        weeklyPlanReset().url,
        { child_id: editing.value.childId, date: editing.value.date },
        { preserveScroll: true, onSuccess: closeEditor },
    );
}

// Stage/unstage a fresh absence locally — committed only on Speichern (see save()).
// Staging clears the note so a fresh (required) reason is typed, not the plan comment.
function stageAbsence(reason) {
    if (form.absence_reason === reason) {
        form.absence_reason = '';
    } else {
        form.absence_reason = reason;
        form.note = '';
    }
}

function cancelAbsence() {
    router.delete(absenceDestroy().url, {
        data: {
            child_id: editing.value.childId,
            from: editing.value.date,
            to: editing.value.date,
        },
        preserveScroll: true,
        onSuccess: closeEditor,
    });
}
</script>

<template>
    <Head :title="$t('weekly.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('weekly.title') }}</h2>
        </template>

        <div class="space-y-8">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <!-- „Geht mit … mit" overview for the parent: their child going with another
                 (+ status), or a child coming home with theirs (confirm inline). -->
            <CompanionNotes :notes="companionNotes" @confirm="answerCompanion" />

            <!-- Current week (effective plan, editable) -->
            <section class="space-y-3" @touchstart="onTouchStart" @touchend="onTouchEnd">
                <WeekNav :week="week" @navigate="goWeek" />

                <!-- Parents see + edit their own children; staff use the timeline below. -->
                <template v-if="!isStaff">
                    <h3 class="text-sm font-semibold text-ink/70">{{ $t('weekly.your_children') }}</h3>

                    <ul v-if="currentWeek.length" class="space-y-3">
                    <li
                        v-for="child in currentWeek"
                        :key="child.id"
                        class="rounded-2xl bg-surface p-4 shadow-sm"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <p class="font-semibold text-ink">
                                {{ child.name }}
                            </p>
                            <span
                                v-if="child.can_manage"
                                class="text-xs text-ink/40"
                            >
                                {{ $t('weekly.tap_to_change') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-5 gap-1.5">
                            <div
                                v-for="(day, i) in child.days"
                                :key="day.date"
                                class="rounded-lg text-center"
                                :class="[
                                    day.past ? 'opacity-40' : '',
                                    weekDays[i].is_today ? 'bg-hort-teal/10 ring-1 ring-hort-teal/40' : '',
                                ]"
                            >
                                <div
                                    class="text-xs font-medium"
                                    :class="weekDays[i].is_today ? 'text-hort-teal-dark' : 'text-ink/40'"
                                >
                                    {{ weekDays[i].label }}<span v-if="weekDays[i].is_today"> · {{ $t('common.today') }}</span>
                                </div>
                                <div
                                    class="text-[11px]"
                                    :class="weekDays[i].is_today ? 'font-semibold text-hort-teal-dark' : 'text-ink/30'"
                                >
                                    {{ weekDays[i].date_label }}
                                </div>
                                <component
                                    :is="day.editable ? 'button' : 'div'"
                                    type="button"
                                    class="relative mt-1 w-full rounded-lg py-2 text-sm font-semibold"
                                    :class="[
                                        day.absent ? 'bg-amber-100 text-amber-700' : cellClass(day),
                                        day.adjusted && !day.absent ? 'ring-2 ring-amber-400' : '',
                                        day.editable ? 'cursor-pointer hover:brightness-95 active:scale-[0.97]' : '',
                                    ]"
                                    :title="day.absent ? day.absent.label : day.comment || undefined"
                                    @click="openCell(child, day, weekDays[i])"
                                >
                                    <template v-if="!day.absent && day.time"><span v-if="day.method === 'sent_home'">🚶&nbsp;</span><span v-if="timePrefix(day.method, day.qualifier)">{{ timePrefix(day.method, day.qualifier) }}&nbsp;</span></template>{{ day.absent ? day.absent.label : (day.time ?? $t('weekly.free')) }}
                                    <span
                                        v-if="day.companion"
                                        class="mt-0.5 block truncate text-[10px] font-normal leading-tight"
                                        :class="[
                                            day.companion.confirmed === true ? 'opacity-70' : 'font-medium',
                                            day.companion.confirmed === false ? 'text-red-700' : '',
                                            day.companion.confirmed === null ? 'text-hort-orange-dark' : '',
                                        ]"
                                    >
                                        {{ $t('weekly.companion_with', { name: day.companion.name }) }}<template v-if="day.companion.confirmed === null"> · {{ $t('weekly.companion_pending') }}</template><template v-else-if="day.companion.confirmed === false"> · {{ $t('weekly.companion_declined') }}</template>
                                    </span>
                                    <span
                                        v-if="day.birthday !== null"
                                        class="mt-0.5 block text-[10px] leading-none"
                                        :title="$t('weekly.birthday_title')"
                                    >
                                        🎂
                                    </span>
                                    <span
                                        v-if="day.excursion"
                                        class="mt-0.5 block text-[10px] leading-none"
                                        :title="day.excursion.name"
                                    >
                                        🚌
                                    </span>
                                    <span
                                        v-else-if="day.comment"
                                        class="mt-0.5 block truncate text-[10px] font-normal leading-tight opacity-70"
                                    >
                                        {{ day.comment }}
                                    </span>
                                </component>
                            </div>
                        </div>

                        <!-- Birthdays, trips and pickup conflicts this week -->
                        <div
                            v-if="child.days.some((d, idx) => d.excursion || d.birthday !== null || homeworkConflict(d, idx))"
                            class="mt-2 space-y-1"
                        >
                            <template v-for="(day, i) in child.days" :key="day.date">
                                <p
                                    v-if="day.birthday !== null"
                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                                >
                                    {{ $t('weekly.birthday_flag', { day: weekDays[i].label, age: day.birthday }) }}
                                </p>
                                <p
                                    v-if="day.conflict"
                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                                >
                                    {{ $t('weekly.pickup_conflict', { day: weekDays[i].label, time: day.time, name: day.excursion.name }) }}<span
                                        v-if="day.excursion.return_at"
                                    >
                                        ({{ $t('weekly.back_return', { time: day.excursion.return_at }) }})</span
                                    >
                                </p>
                                <p
                                    v-else-if="day.excursion"
                                    class="rounded-lg bg-hort-purple/10 px-2 py-1 text-xs font-medium text-hort-purple"
                                >
                                    {{ $t('weekly.excursion_flag', { day: weekDays[i].label, name: day.excursion.name }) }}<span
                                        v-if="day.excursion.depart_at"
                                    >
                                        ({{ day.excursion.depart_at }}–{{
                                            day.excursion.return_at
                                        }})</span
                                    >
                                </p>
                                <p
                                    v-if="homeworkConflict(day, i)"
                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                                >
                                    {{ $t('weekly.homework_conflict', { day: weekDays[i].label, time: day.time }) }}
                                </p>
                            </template>
                        </div>
                    </li>
                </ul>

                    <p
                        v-else
                        class="rounded-2xl border-2 border-dashed border-ink/15 p-6 text-center text-sm text-ink/50"
                    >
                        {{ $t('weekly.no_child_assigned') }}
                    </p>
                </template>

                <!-- Whole week, all children: effective plan + this week's programs -->
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-ink/70">
                        {{ $t('weekly.whole_week') }}
                    </h3>
                    <WeekTimetable
                        v-if="weekTimetable.length || program.some((p) => p && (p.lunch || p.activity || p.homework_start))"
                        :rows="weekTimetable"
                        :columns="weekColumns"
                        :program="program"
                        :activities="activities"
                        :editable="isStaff"
                        @edit="openFromTimeline"
                    />
                    <p
                        v-else
                        class="rounded-2xl border-2 border-dashed border-ink/15 p-6 text-center text-sm text-ink/50"
                    >
                        {{ $t('weekly.empty_week') }}
                    </p>

                    <!-- Reported away this week (absent kids are off the grid above) -->
                    <div
                        v-if="weekAbsences.some((d) => d.length)"
                        class="rounded-2xl bg-amber-50 p-3 text-amber-800 shadow-sm"
                    >
                        <p class="mb-1 text-sm font-semibold">{{ $t('weekly.not_coming_heading') }}</p>
                        <template v-for="(day, i) in weekAbsences" :key="i">
                            <p v-if="day.length" class="text-xs leading-relaxed">
                                <span class="font-semibold">{{ weekColumns[i].label }}:</span>
                                {{ absenceLine(day) }}
                            </p>
                        </template>
                    </div>
                </div>

                <div
                    v-if="currentWeek.length"
                    class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-ink/60"
                >
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-hort-teal/60" />
                        {{ $t('weekly.legend_picked_up') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-hort-orange/60" />
                        🚶 {{ $t('weekly.legend_alone') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full ring-2 ring-amber-400" />
                        {{ $t('weekly.legend_changed') }}
                    </span>
                </div>
            </section>

            <!-- Pointer to the standard plan (edit the regular weekly times there) -->
            <p class="border-t border-ink/5 pt-4 text-center text-sm text-ink/50">
                {{ $t('weekly.to_standard_hint') }}
                <Link
                    :href="standardPlan().url"
                    class="font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                >
                    {{ $t('weekly.to_standard_link') }} →
                </Link>
            </p>
        </div>

        <!-- Day editor -->
        <Modal :show="editing !== null" max-width="sm" @close="closeEditor">
            <div v-if="editing" class="space-y-5 p-6">
                <div>
                    <h2 class="text-lg font-semibold text-ink">
                        {{ editing.childName }}
                    </h2>
                    <p class="text-sm text-ink/50">
                        {{ $t('weekly.editor_subtitle', { label: editing.label }) }}
                    </p>
                </div>

                <!-- Krankmeldung / Abwesenheit — first: it overrides the plan below -->
                <div class="rounded-lg bg-canvas p-3">
                    <template v-if="editing.absent">
                        <p class="text-sm font-medium text-amber-700">
                            {{ $t('weekly.reported_as', { label: editing.absent.label }) }}
                        </p>
                        <p v-if="editing.absent.comment" class="mt-0.5 text-sm text-ink/60">
                            {{ editing.absent.comment }}
                        </p>
                        <button
                            type="button"
                            class="mt-2 text-sm font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                            @click="cancelAbsence"
                        >
                            {{ $t('weekly.cancel_absence') }}
                        </button>
                    </template>
                    <template v-else>
                        <p class="text-sm text-ink/60">{{ $t('weekly.not_here_today') }}</p>
                        <div class="mt-2 flex gap-2">
                            <button
                                type="button"
                                class="rounded-md border px-4 py-2 text-xs font-semibold uppercase tracking-widest transition"
                                :class="form.absence_reason === 'sick'
                                    ? 'border-hort-orange bg-hort-orange/15 text-ink ring-1 ring-hort-orange'
                                    : 'border-ink/20 text-ink/80 hover:bg-ink/5'"
                                @click="stageAbsence('sick')"
                            >
                                {{ $t('weekly.report_sick') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md border px-4 py-2 text-xs font-semibold uppercase tracking-widest transition"
                                :class="form.absence_reason === 'away'
                                    ? 'border-hort-orange bg-hort-orange/15 text-ink ring-1 ring-hort-orange'
                                    : 'border-ink/20 text-ink/80 hover:bg-ink/5'"
                                @click="stageAbsence('away')"
                            >
                                {{ $t('weekly.report_away') }}
                            </button>
                        </div>
                        <p v-if="stagingAbsence" class="mt-2 text-xs font-medium text-hort-orange-dark">
                            {{ $t('weekly.absence_needs_comment') }}
                        </p>
                    </template>
                </div>

                <!-- Pickup plan — disabled while the child is (or is being) reported away -->
                <fieldset
                    :disabled="!!editing.absent || stagingAbsence"
                    class="space-y-5"
                    :class="editing.absent || stagingAbsence ? 'opacity-40' : ''"
                >
                    <!-- The time is hidden for „geht mit … mit" (mirrored from the companion). -->
                    <div v-if="!goingWithChild">
                        <InputLabel for="time" :value="$t('weekly.time_label')" />
                        <TimeSelect
                            id="time"
                            v-model="form.planned_time"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <div>
                        <InputLabel for="method" :value="$t('weekly.method_label')" />
                        <select
                            id="method"
                            v-model="form.planned_method"
                            class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal disabled:bg-ink/5 disabled:text-ink/40"
                        >
                            <option value="">{{ $t('weekly.method_open') }}</option>
                            <option
                                v-for="o in methodOptions"
                                :key="o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                    </div>

                    <!-- „Geht mit … mit": pick the companion; the time is taken from them. -->
                    <div v-if="goingWithChild">
                        <InputLabel for="companion" :value="$t('weekly.companion_label')" />
                        <select
                            id="companion"
                            v-model="form.companion_child_id"
                            class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                        >
                            <option value="">{{ $t('weekly.companion_open') }}</option>
                            <option
                                v-for="c in companionChoices"
                                :key="c.id"
                                :value="c.id"
                                :disabled="!c.available"
                            >
                                {{ c.name }}{{ c.available ? '' : ` — ${$t('weekly.companion_not_pickedup')}` }}
                            </option>
                        </select>
                        <!-- Empty state: explain where the time went before a child is chosen. -->
                        <p v-if="!selectedCompanionName" class="mt-1 text-xs text-ink/50">
                            {{ $t('weekly.companion_empty_hint') }}
                        </p>
                        <template v-else>
                            <p
                                v-if="selectedCompanionUnavailable"
                                class="mt-1 text-xs font-medium text-red-700"
                            >
                                {{ $t('weekly.companion_unavailable') }}
                            </p>
                            <template v-else>
                                <p class="mt-1 text-xs text-ink/50">
                                    {{ $t('weekly.companion_mirror_hint', { name: selectedCompanionName }) }}<span v-if="selectedCompanionTime"> ({{ selectedCompanionTime }})</span>
                                </p>
                                <p class="mt-0.5 text-xs font-medium text-hort-orange-dark">
                                    {{ $t('weekly.companion_confirm_hint', { name: selectedCompanionName }) }}
                                </p>
                            </template>
                        </template>
                    </div>

                    <!-- „Geht allein": what the time means (bis / genau um / ab) -->
                    <div v-if="form.planned_method === 'sent_home'">
                        <InputLabel for="qualifier" :value="$t('weekly.qualifier_label')" />
                        <select
                            id="qualifier"
                            v-model="form.time_qualifier"
                            class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                        >
                            <option
                                v-for="o in qualifierOptions"
                                :key="o.value"
                                :value="o.value"
                            >
                                {{ o.label }}
                            </option>
                        </select>
                    </div>
                </fieldset>

                <div>
                    <InputLabel
                        for="note"
                        :value="stagingAbsence ? $t('weekly.reason_label') : $t('common.note')"
                    />
                    <TextInput
                        id="note"
                        v-model="form.note"
                        type="text"
                        maxlength="255"
                        class="mt-1 block w-full"
                        :placeholder="stagingAbsence ? $t('weekly.reason_placeholder') : $t('weekly.note_placeholder')"
                    />
                    <p
                        class="mt-1 text-xs"
                        :class="stagingAbsence ? 'font-medium text-hort-orange-dark' : 'text-ink/50'"
                    >
                        {{ stagingAbsence ? $t('weekly.reason_hint') : $t('weekly.note_hint') }}
                    </p>
                </div>

                <p
                    v-if="saveError"
                    class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700"
                >
                    {{ saveError }}
                </p>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <button
                        type="button"
                        class="text-sm font-medium text-ink/50 underline-offset-2 hover:underline"
                        @click="resetDay"
                    >
                        {{ $t('weekly.reset_to_standard') }}
                    </button>
                    <div class="flex gap-3">
                        <SecondaryButton @click="closeEditor">
                            {{ $t('common.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton :disabled="!canSave || saving" @click="save">{{ saving ? $t('common.saving') : $t('common.save') }}</PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
