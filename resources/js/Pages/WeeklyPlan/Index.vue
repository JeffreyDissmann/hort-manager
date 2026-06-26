<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TimeSelect from '@/Components/TimeSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    week: { type: Object, default: () => ({}) },
    weekDays: { type: Array, default: () => [] },
    currentWeek: { type: Array, default: () => [] },
    activities: { type: Array, default: () => [] },
    program: { type: Array, default: () => [] },
    standard: { type: Array, default: () => [] },
    methodOptions: { type: Array, default: () => [] },
});

function goWeek(date) {
    router.get(
        route('weekly-plan', date ? { week: date } : {}),
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
const standardWeekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

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

function cellClass(day) {
    if (!day.time) {
        return 'bg-hort-navy/5 text-hort-navy/30';
    }
    return day.method === 'sent_home'
        ? 'bg-hort-purple/15 text-hort-purple'
        : 'bg-hort-teal/20 text-hort-teal-dark';
}

function chipClass(method) {
    return method === 'sent_home'
        ? 'bg-hort-purple/15 text-hort-purple'
        : 'bg-hort-teal/25 text-hort-teal-dark';
}

// --- Inline editor (modal) ---
const editing = ref(null); // { childId, childName, date, label }
const form = reactive({ planned_time: '', planned_method: '', note: '' });

function openCell(child, day, dayMeta) {
    if (!day.editable) {
        return;
    }
    editing.value = {
        childId: child.id,
        childName: child.name,
        date: day.date,
        label: `${dayMeta.label} ${dayMeta.date_label}`,
    };
    form.planned_time = day.time ?? '';
    form.planned_method = day.method ?? '';
    form.note = day.note ?? '';
}

function closeEditor() {
    editing.value = null;
}

function save() {
    router.patch(
        route('weekly-plan.adjust'),
        {
            child_id: editing.value.childId,
            date: editing.value.date,
            planned_time: form.planned_time || null,
            planned_method: form.planned_method || null,
            note: form.note || null,
        },
        { preserveScroll: true, onSuccess: closeEditor },
    );
}

function resetDay() {
    router.patch(
        route('weekly-plan.reset'),
        { child_id: editing.value.childId, date: editing.value.date },
        { preserveScroll: true, onSuccess: closeEditor },
    );
}
</script>

<template>
    <Head title="Abholplan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Abholplan</h2>
        </template>

        <div class="space-y-8">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <!-- Current week (effective plan, editable) -->
            <section class="space-y-3" @touchstart="onTouchStart" @touchend="onTouchEnd">
                <div class="flex items-center justify-between gap-2">
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

                <ul v-if="currentWeek.length" class="space-y-3">
                    <li
                        v-for="child in currentWeek"
                        :key="child.id"
                        class="rounded-2xl bg-white p-4 shadow-sm"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <p class="font-semibold text-hort-navy">
                                {{ child.name }}
                            </p>
                            <span
                                v-if="child.can_manage"
                                class="text-xs text-hort-navy/40"
                            >
                                Tippen zum Ändern
                            </span>
                        </div>

                        <div class="grid grid-cols-5 gap-1.5">
                            <div
                                v-for="(day, i) in child.days"
                                :key="day.date"
                                class="text-center"
                                :class="day.past ? 'opacity-40' : ''"
                            >
                                <div class="text-[11px] font-medium text-hort-navy/40">
                                    {{ weekDays[i].label }}
                                </div>
                                <div class="text-[10px] text-hort-navy/30">
                                    {{ weekDays[i].date_label }}
                                </div>
                                <component
                                    :is="day.editable ? 'button' : 'div'"
                                    type="button"
                                    class="relative mt-1 w-full rounded-lg py-2 text-xs font-semibold"
                                    :class="[
                                        cellClass(day),
                                        day.adjusted ? 'ring-2 ring-amber-400' : '',
                                        day.editable ? 'cursor-pointer hover:brightness-95 active:scale-[0.97]' : '',
                                    ]"
                                    :title="day.comment || undefined"
                                    @click="openCell(child, day, weekDays[i])"
                                >
                                    {{ day.time ?? 'frei' }}
                                    <span
                                        v-if="day.excursion"
                                        class="mt-0.5 block text-[10px] leading-none"
                                        :title="day.excursion.name"
                                    >
                                        🚌
                                    </span>
                                    <span
                                        v-else-if="day.comment"
                                        class="mt-0.5 block truncate text-[9px] font-normal leading-tight opacity-70"
                                    >
                                        {{ day.comment }}
                                    </span>
                                </component>
                            </div>
                        </div>

                        <!-- Trips this week + pickup conflicts -->
                        <div
                            v-if="child.days.some((d, idx) => d.excursion || homeworkConflict(d, idx))"
                            class="mt-2 space-y-1"
                        >
                            <template v-for="(day, i) in child.days" :key="day.date">
                                <p
                                    v-if="day.conflict"
                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                                >
                                    {{ weekDays[i].label }}: Abholung
                                    {{ day.time }} liegt im {{ day.excursion.name }}<span
                                        v-if="day.excursion.return_at"
                                    >
                                        (zurück {{ day.excursion.return_at }})</span
                                    >
                                </p>
                                <p
                                    v-else-if="day.excursion"
                                    class="rounded-lg bg-hort-purple/10 px-2 py-1 text-xs font-medium text-hort-purple"
                                >
                                    🚌 {{ weekDays[i].label }}:
                                    {{ day.excursion.name }}<span
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
                                    {{ weekDays[i].label }}: Abholung {{ day.time }}
                                    liegt in der Hausaufgabenzeit
                                </p>
                            </template>
                        </div>
                    </li>
                </ul>

                <p
                    v-else
                    class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
                >
                    {{
                        isStaff
                            ? 'Noch keine Kinder angelegt.'
                            : 'Dir ist noch kein Kind zugeordnet.'
                    }}
                </p>

                <!-- Week overview: program (lunch + activity) and excursions per day -->
                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <p class="mb-2 text-sm font-semibold text-hort-navy/70">
                        Essen, Aktivität & Ausflüge
                    </p>
                    <div class="grid grid-cols-5 gap-1.5">
                        <div
                            v-for="(day, i) in weekDays"
                            :key="day.date"
                            class="space-y-1 rounded-lg bg-hort-sand p-1.5 text-center"
                        >
                            <div class="text-[11px] font-medium text-hort-navy/40">
                                {{ day.label }}
                            </div>

                            <div
                                v-if="program[i] && program[i].lunch"
                                class="text-[10px] leading-tight text-hort-navy/80"
                                :title="program[i].lunch"
                            >
                                {{ program[i].lunch }}
                            </div>
                            <div
                                v-if="program[i] && program[i].activity"
                                class="text-[10px] leading-tight text-hort-purple"
                                :title="program[i].activity"
                            >
                                {{ program[i].activity }}
                            </div>
                            <div
                                v-if="program[i] && program[i].homework_start"
                                class="text-[10px] leading-tight text-hort-navy/50"
                            >
                                HA {{ program[i].homework_start }}<span
                                    v-if="program[i].homework_end"
                                    >–{{ program[i].homework_end }}</span
                                >
                            </div>

                            <div
                                v-for="(activity, j) in activities[i]"
                                :key="'ex-' + j"
                                class="rounded-md bg-hort-purple/15 px-1 py-0.5 text-[10px] font-semibold leading-tight text-hort-purple"
                                :title="activity.name"
                            >
                                <span class="block truncate">🚌 {{ activity.name }}</span>
                                <span
                                    v-if="activity.depart_at"
                                    class="block font-normal opacity-80"
                                >
                                    {{ activity.depart_at }}–{{ activity.return_at }}
                                </span>
                            </div>

                            <div
                                v-if="
                                    !activities[i]?.length &&
                                    !(
                                        program[i] &&
                                        (program[i].lunch ||
                                            program[i].activity ||
                                            program[i].homework_start)
                                    )
                                "
                                class="text-[10px] text-hort-navy/20"
                            >
                                –
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="currentWeek.length"
                    class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-hort-navy/60"
                >
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-hort-teal/60" />
                        wird abgeholt
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-hort-purple/50" />
                        geht allein
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full ring-2 ring-amber-400" />
                        geändert
                    </span>
                </div>
            </section>

            <!-- Standard Stammplan timetable -->
            <section class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-hort-navy/50">
                    Standard-Plan
                </h3>
                <p class="text-sm text-hort-navy/60">
                    Der reguläre Wochen-Stammplan aller Kinder – die normalen
                    Abholzeiten ohne Änderungen.
                    <Link
                        :href="route('children.index')"
                        class="font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                    >
                        {{
                            isStaff
                                ? 'Stammplan unter „Kinder“ bearbeiten'
                                : 'Stammplan unter „Meine Kinder“ ändern'
                        }}
                    </Link>
                </p>

                <div
                    v-if="standard.length"
                    class="overflow-x-auto rounded-2xl bg-white p-2 shadow-sm"
                >
                    <div class="min-w-[20rem]">
                        <div class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] gap-1 border-b border-hort-navy/10 pb-1">
                            <div></div>
                            <div
                                v-for="day in standardWeekdays"
                                :key="day"
                                class="py-1 text-center text-xs font-semibold text-hort-navy/50"
                            >
                                {{ day }}
                            </div>
                        </div>

                        <div
                            v-for="row in standard"
                            :key="row.time"
                            class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] items-stretch gap-1 border-b border-hort-navy/5 last:border-0"
                        >
                            <div class="flex items-start justify-end pr-1 pt-1.5 text-[11px] font-medium tabular-nums text-hort-navy/40">
                                {{ row.time }}
                            </div>
                            <div
                                v-for="(kids, i) in row.days"
                                :key="i"
                                class="space-y-1 py-1"
                            >
                                <div
                                    v-for="kid in kids"
                                    :key="kid.id"
                                    class="rounded-md px-1.5 py-1 text-center text-[11px] font-semibold leading-tight"
                                    :class="chipClass(kid.method)"
                                    :title="kid.comment || undefined"
                                >
                                    <span class="block truncate">{{ kid.name }}</span>
                                    <span
                                        v-if="kid.comment"
                                        class="block truncate text-[9px] font-normal opacity-70"
                                    >
                                        {{ kid.comment }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p
                    v-else
                    class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
                >
                    Noch keine Abholzeiten im Stammplan hinterlegt.
                </p>
            </section>
        </div>

        <!-- Day editor -->
        <Modal :show="editing !== null" max-width="sm" @close="closeEditor">
            <div v-if="editing" class="space-y-5 p-6">
                <div>
                    <h2 class="text-lg font-semibold text-hort-navy">
                        {{ editing.childName }}
                    </h2>
                    <p class="text-sm text-hort-navy/50">
                        {{ editing.label }} – nur für diese Woche
                    </p>
                </div>

                <div>
                    <InputLabel for="time" value="Uhrzeit (leer = kommt nicht)" />
                    <TimeSelect
                        id="time"
                        v-model="form.planned_time"
                        class="mt-1 block w-full"
                    />
                </div>

                <div>
                    <InputLabel for="method" value="Art" />
                    <select
                        id="method"
                        v-model="form.planned_method"
                        :disabled="!form.planned_time"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-hort-teal focus:ring-hort-teal disabled:bg-gray-100 disabled:text-gray-400"
                    >
                        <option value="">— offen —</option>
                        <option
                            v-for="o in methodOptions"
                            :key="o.value"
                            :value="o.value"
                        >
                            {{ o.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <InputLabel for="note" value="Kommentar" />
                    <TextInput
                        id="note"
                        v-model="form.note"
                        type="text"
                        maxlength="255"
                        class="mt-1 block w-full"
                        placeholder="z. B. wegen Arzttermin"
                    />
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <button
                        type="button"
                        class="text-sm font-medium text-hort-navy/50 underline-offset-2 hover:underline"
                        @click="resetDay"
                    >
                        Auf Standard
                    </button>
                    <div class="flex gap-3">
                        <SecondaryButton @click="closeEditor">
                            Abbrechen
                        </SecondaryButton>
                        <PrimaryButton @click="save">Speichern</PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
