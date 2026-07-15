<script setup>
import { mark as boardMark } from '@/routes/board';
import { store as absenceStore, destroy as absenceDestroy } from '@/routes/absences';
import { live as excursionLive } from '@/routes/excursions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CollapsibleChips from '@/Components/CollapsibleChips.vue';
import CompanionNotes from '@/Components/CompanionNotes.vue';
import DayEditor from '@/Components/DayEditor.vue';
import { PencilSquareIcon } from '@heroicons/vue/24/outline';
import { confirm as companionConfirm } from '@/routes/companion';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
    date: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    companionNotes: { type: Array, default: () => [] },
    absent: { type: Array, default: () => [] },
    hortfrei: { type: Array, default: () => [] },
    excursions: { type: Array, default: () => [] },
    program: { type: Object, default: null },
    canMark: { type: Boolean, default: false },
    children: { type: Array, default: () => [] },
    methodOptions: { type: Array, default: () => [] },
    qualifierOptions: { type: Array, default: () => [] },
});

// Confirm/decline another child going home with one of ours (from the notes panel).
function answerCompanion(id, confirmed) {
    router.patch(companionConfirm(id).url, { confirmed }, { preserveScroll: true });
}

const flash = computed(() => usePage().props.flash?.status);
const isParent = computed(() => usePage().props.auth?.user?.role === 'parent');

// Personal view preference (parents): show all children or only my own.
// Persisted per device in localStorage; defaults to showing all.
const showAll = ref(
    typeof localStorage === 'undefined' ||
        localStorage.getItem('board.showAll') !== 'false',
);
watch(showAll, (value) => {
    localStorage.setItem('board.showAll', value ? 'true' : 'false');
});

const visibleRows = computed(() => {
    if (!isParent.value || showAll.value) {
        return props.rows;
    }
    return props.rows.filter((r) => r.is_own);
});

const methodLabels = computed(() =>
    Object.fromEntries(props.methodOptions.map((o) => [o.value, o.label])),
);

// Counts always reflect the whole Hort, regardless of the "only mine" filter.
const counts = computed(() => {
    let present = 0;
    let left = 0;
    let excursion = 0;
    for (const r of props.rows) {
        if (r.status === 'present') present++;
        else left++;
        if (r.excursion) excursion++;
    }
    return { present, left, excursion };
});

// Only the safety-relevant "goes home alone" case gets a glanceable icon.
function methodIcon(method) {
    return method === 'sent_home' ? '🚶' : '';
}

function planLabel(row) {
    // „geht mit … mit": show the mirrored time + „mit B" rather than the long label.
    if (row.planned_method === 'with_child' && row.companion) {
        const withText = t('weekly.companion_with', { name: row.companion.name });
        return row.planned_time ? `${row.planned_time} · ${withText}` : withText;
    }
    const method = methodLabels.value[row.planned_method];
    if (!method) {
        return row.planned_time;
    }
    const icon = methodIcon(row.planned_method);
    // „geht allein" may carry a bis/ab prefix on the time (default „um" stays implicit).
    const time = row.qualifier_prefix ? `${row.qualifier_prefix} ${row.planned_time}` : row.planned_time;
    return `${icon ? icon + ' ' : ''}${time} · ${method}`;
}

function toMinutes(time) {
    return parseInt(time.slice(0, 2), 10) * 60 + parseInt(time.slice(3, 5), 10);
}

// Pickup falls inside today's homework slot.
function homeworkConflict(row) {
    const hw = props.program;
    if (!row.planned_time || !hw || !hw.homework_start || !hw.homework_end) {
        return false;
    }
    const pickup = toMinutes(row.planned_time);
    return pickup >= toMinutes(hw.homework_start) && pickup < toMinutes(hw.homework_end);
}

// The board grouped by time: everything happening at the same time — children
// leaving, and the homework window (at its start time) — shares one time slot.
const boardBlocks = computed(() => {
    const byTime = new Map();
    const slotFor = (time) => {
        if (!byTime.has(time)) {
            byTime.set(time, { time, rows: [], homeworkOnly: false });
        }
        return byTime.get(time);
    };

    for (const row of visibleRows.value) {
        slotFor(row.planned_time ?? null).rows.push(row);
    }

    // Ascending by time; slots without a fixed time (null) sort last.
    const blocks = [...byTime.values()].sort((a, b) => {
        if (a.time === b.time) return 0;
        if (a.time === null) return 1;
        if (b.time === null) return -1;
        return a.time < b.time ? -1 : 1;
    });

    // If a pickup overlaps the homework window, a vertical bar covers those rows.
    // Otherwise show homework as a horizontal card at its start time.
    const hw = props.program;
    if (hw?.homework_start && hw?.homework_end) {
        const overlaps = (b) =>
            b.rows.length && b.time !== null && b.time >= hw.homework_start && b.time < hw.homework_end;
        if (!blocks.some(overlaps)) {
            const card = {
                time: hw.homework_start,
                rows: [],
                homeworkCard: { start: hw.homework_start, end: hw.homework_end },
            };
            const at = blocks.findIndex((b) => b.time !== null && b.time >= hw.homework_start);
            at === -1 ? blocks.push(card) : blocks.splice(at, 0, card);
        }
    }

    return blocks;
});

// The homework bar's grid-row span — only when pickups overlap the window.
const homeworkSpan = computed(() => {
    const hw = props.program;
    if (!hw?.homework_start || !hw?.homework_end) {
        return null;
    }
    const covered = (b) =>
        b.rows.length && b.time !== null && b.time >= hw.homework_start && b.time < hw.homework_end;
    const idxs = [];
    boardBlocks.value.forEach((b, i) => {
        if (covered(b)) {
            idxs.push(i);
        }
    });
    if (!idxs.length) {
        return null;
    }
    return { rowStart: idxs[0] + 1, span: idxs[idxs.length - 1] - idxs[0] + 1 };
});

// A block only shifts right (making room for the homework bar) when it's inside
// the homework window; otherwise it stays flush left across the full width.
function blockInHomework(i) {
    const s = homeworkSpan.value;
    return !!s && i + 1 >= s.rowStart && i + 1 < s.rowStart + s.span;
}

// Pickup falls inside the child's excursion window.
function excursionConflict(row) {
    const ex = row.excursion;
    if (!ex || !row.planned_time || !ex.return_at) {
        return false;
    }
    const pickup = toMinutes(row.planned_time);
    const depart = ex.depart_at ? toMinutes(ex.depart_at) : 0;
    return pickup >= depart && pickup < toMinutes(ex.return_at);
}

// Guards every board mutation so a double-tap on a phone can't fire it twice.
const submitting = ref(false);
const guard = {
    preserveScroll: true,
    onStart: () => { submitting.value = true; },
    onFinish: () => { submitting.value = false; },
};

function mark(row, status) {
    if (submitting.value) {
        return;
    }
    router.patch(boardMark(row.id).url, { status }, guard);
}

function liveEvent(excursion, event) {
    if (submitting.value) {
        return;
    }
    router.patch(excursionLive(excursion.id).url, { event }, guard);
}

// Report a child away for today straight from the board. Like the Wochenplan
// editor, this is staged: tapping Krank/Kommt nicht reveals a required reason
// field, and nothing is sent until the user confirms — reporting a child away
// also unwinds any „geht mit … mit" arrangement, so it shouldn't fire on a mis-tap.
const absenceRow = ref(null);
const absenceReason = ref('');
const absenceComment = ref('');
const absenceSaving = ref(false);

function stageAbsence(row, reason) {
    absenceRow.value = row.id;
    absenceReason.value = reason;
    absenceComment.value = '';
}

function cancelAbsence() {
    absenceRow.value = null;
    absenceReason.value = '';
    absenceComment.value = '';
}

function submitAbsence(row) {
    if (!absenceComment.value.trim()) {
        return;
    }
    router.post(
        absenceStore().url,
        {
            child_id: row.child_id,
            from: props.date.iso,
            to: props.date.iso,
            reason: absenceReason.value,
            comment: absenceComment.value.trim(),
        },
        {
            preserveScroll: true,
            onStart: () => { absenceSaving.value = true; },
            onFinish: () => { absenceSaving.value = false; },
            onSuccess: cancelAbsence,
        },
    );
}

// Undo an absence from the „Heute abwesend" strip (staff or the child's parent).
function clearAbsence(a) {
    router.delete(absenceDestroy().url, {
        data: { child_id: a.child_id, from: props.date.iso, to: props.date.iso },
        preserveScroll: true,
    });
}

// --- Day editor (shared popup) — edits one child on today's date ---
const dayEditor = ref(null);

function todayMeta() {
    return { label: props.date.label, date_label: '' };
}

// Edit an existing board row (the „Abholzeit ändern" popup).
function editRow(row) {
    dayEditor.value?.open(
        { id: row.child_id, name: row.name },
        {
            date: props.date.iso,
            editable: true,
            time: row.planned_time,
            method: row.planned_method,
            qualifier: row.qualifier,
            companion: row.companion,
            note: row.note,
        },
        todayMeta(),
    );
}

// Add a „Hortfrei" child to today straight from the summary pill (empty plan).
function editHortfrei(child) {
    dayEditor.value?.open(
        { id: child.id, name: child.name },
        { date: props.date.iso, editable: true, time: null, method: null, qualifier: 'at', companion: null, note: null },
        todayMeta(),
    );
}
</script>

<template>
    <Head :title="$t('board.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-ink">
                    {{ date.is_today ? $t('common.today') : $t('board.next_hort_day') }}
                </h2>
                <p class="text-sm text-ink/50">{{ date.label }}</p>
            </div>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <!-- „Geht mit … mit" overview for the parent (staff use the plan display). -->
            <CompanionNotes :notes="companionNotes" @confirm="answerCompanion" />

            <!-- Today's program (lunch + activity) -->
            <!-- Lunch + activity; homework now appears inline in the pickup list. -->
            <div
                v-if="program && (program.lunch || program.activity)"
                class="rounded-2xl bg-surface p-4 shadow-sm"
            >
                <p v-if="program.lunch" class="text-sm text-ink">
                    <span class="font-semibold">{{ $t('board.lunch_label') }}</span>
                    {{ program.lunch }}
                </p>
                <p
                    v-if="program.activity"
                    class="text-sm text-ink"
                    :class="program.lunch ? 'mt-1' : ''"
                >
                    <span class="font-semibold">{{ $t('board.activity_label') }}</span>
                    {{ program.activity }}
                </p>
            </div>

            <!-- Summary (left) + parent filter (right), one line -->
            <div class="flex flex-wrap items-center gap-2">
                <div
                    v-if="rows.length"
                    class="flex gap-2 text-sm font-semibold"
                >
                    <span class="rounded-xl bg-surface px-3 py-2 text-ink shadow-sm">
                        {{ $t('board.count_present', { n: counts.present }) }}
                    </span>
                    <span class="rounded-xl bg-surface px-3 py-2 text-ink/50 shadow-sm">
                        {{ $t('board.count_left', { n: counts.left }) }}
                    </span>
                    <span
                        v-if="counts.excursion"
                        class="rounded-xl bg-surface px-3 py-2 text-hort-purple shadow-sm"
                    >
                        {{ $t('board.count_excursion', { n: counts.excursion }) }}
                    </span>
                </div>

                <div
                    v-if="isParent"
                    class="ml-auto inline-flex rounded-lg bg-surface p-0.5 text-xs font-semibold shadow-sm"
                >
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 transition"
                        :class="showAll ? 'bg-hort-teal text-hort-navy' : 'text-ink/50'"
                        @click="showAll = true"
                    >
                        {{ $t('common.all') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 transition"
                        :class="!showAll ? 'bg-hort-teal text-hort-navy' : 'text-ink/50'"
                        @click="showAll = false"
                    >
                        {{ $t('board.only_mine') }}
                    </button>
                </div>
            </div>

            <!-- Not at the Hort today — one block combining reported absences (amber,
                 needs attention) and regular „Hortfrei" days (muted, expected). -->
            <div
                v-if="absent.length || hortfrei.length"
                class="space-y-2 rounded-2xl bg-ink/5 p-4 text-sm"
            >
                <div v-if="absent.length">
                    <p class="mb-1 font-semibold text-amber-800">{{ $t('board.absent_today') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="(a, i) in absent"
                            :key="i"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800"
                        >
                            {{ a.name }} · {{ a.reason_label }}<span v-if="a.comment" class="font-normal opacity-80"> · {{ a.comment }}</span>
                            <button
                                v-if="a.can_manage"
                                type="button"
                                class="text-amber-700/70 underline-offset-2 hover:text-amber-900 hover:underline"
                                @click="clearAbsence(a)"
                            >
                                {{ $t('board.clear_absence') }}
                            </button>
                        </span>
                    </div>
                </div>

                <div
                    v-if="hortfrei.length"
                    class="flex flex-wrap items-center gap-1.5 text-ink/50"
                    :class="absent.length ? 'border-t border-ink/10 pt-2' : ''"
                >
                    <span class="font-medium">{{ $t('board.hortfrei_today') }}:</span>
                    <template v-for="c in hortfrei" :key="c.id">
                        <button
                            v-if="c.can_manage"
                            type="button"
                            class="rounded-md bg-ink/10 px-2 py-0.5 text-xs font-medium text-ink/60 transition hover:bg-ink/20 hover:text-ink"
                            @click="editHortfrei(c)"
                        >
                            {{ c.name }}
                        </button>
                        <span v-else class="text-xs">{{ c.name }}</span>
                    </template>
                </div>
            </div>

            <!-- Today's excursions: staff flip the live state (losgegangen / zurück) -->
            <div v-if="excursions.length" class="space-y-2">
                <div
                    v-for="ex in excursions"
                    :key="ex.id"
                    class="rounded-2xl bg-hort-purple/10 p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-hort-purple">
                                🚌 {{ ex.name }}
                            </p>
                            <p class="mt-0.5 text-sm text-ink/60">
                                {{ $t('board.children_count', { n: ex.child_count }) }}<span
                                    v-if="ex.depart_at"
                                >
                                    · {{ ex.depart_at }}<span v-if="ex.return_at"
                                        >–{{ ex.return_at }}</span
                                    >
                                    {{ $t('common.oclock') }}</span
                                >
                            </p>
                            <p
                                v-if="ex.state === 'away'"
                                class="mt-1 text-sm font-medium text-hort-purple"
                            >
                                {{ $t('board.away_since', { time: ex.departed_at }) }}
                            </p>
                            <p
                                v-else-if="ex.state === 'back'"
                                class="mt-1 text-sm font-medium text-hort-teal-dark"
                            >
                                {{ $t('board.back_at', { time: ex.returned_at }) }}
                            </p>
                        </div>

                        <div v-if="canMark" class="shrink-0">
                            <button
                                v-if="ex.state === 'planned'"
                                type="button"
                                class="rounded-xl bg-hort-purple px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 active:scale-[0.98]"
                                @click="liveEvent(ex, 'depart')"
                            >
                                {{ $t('board.departed_button') }}
                            </button>
                            <button
                                v-else-if="ex.state === 'away'"
                                type="button"
                                class="rounded-xl bg-hort-teal px-4 py-2 text-sm font-semibold text-hort-navy transition hover:bg-hort-teal-dark active:scale-[0.98]"
                                @click="liveEvent(ex, 'return')"
                            >
                                {{ $t('board.return_button') }}
                            </button>
                        </div>
                    </div>

                    <!-- Who's on the trip (collapsible, like the Ausflüge page) -->
                    <CollapsibleChips
                        v-if="ex.children.length"
                        :open-label="$t('board.hide_children')"
                        :closed-label="$t('board.show_all_children', { n: ex.children.length })"
                    >
                        <span
                            v-for="name in ex.children"
                            :key="name"
                            class="rounded-md bg-surface/70 px-2 py-0.5 text-xs font-medium text-ink/70"
                        >
                            {{ name }}
                        </span>
                    </CollapsibleChips>

                    <div v-if="canMark && ex.state !== 'planned'" class="mt-2 text-right">
                        <button
                            type="button"
                            class="text-xs font-medium text-ink/40 underline-offset-2 hover:underline"
                            @click="liveEvent(ex, ex.state === 'back' ? 'undo_return' : 'undo_depart')"
                        >
                            {{ $t('common.undo') }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="visibleRows.length"
                class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-6"
                style="grid-auto-rows: max-content"
            >
                <!-- Homework as a side bar spanning the time slots it covers -->
                <div
                    v-if="homeworkSpan && program"
                    class="flex flex-col items-center gap-1 rounded-xl border border-dashed border-amber-300 bg-amber-50 px-1.5 py-2 text-amber-700"
                    :style="{ gridColumn: 1, gridRow: `${homeworkSpan.rowStart} / span ${homeworkSpan.span}` }"
                    :title="`${$t('board.homework')} ${program.homework_start}–${program.homework_end || ''}`"
                >
                    <span class="text-base leading-none">📚</span>
                    <span class="text-[10px] font-semibold [writing-mode:vertical-rl]">
                        {{ program.homework_start }}<span v-if="program.homework_end">–{{ program.homework_end }}</span> {{ $t('common.oclock') }}
                    </span>
                </div>

                <template v-for="(block, i) in boardBlocks" :key="block.time ?? 'none'">
                    <div
                        class="min-w-0"
                        :style="{ gridColumn: blockInHomework(i) ? 2 : '1 / -1', gridRow: i + 1 }"
                    >
                        <!-- No pickup overlaps the window → homework as a horizontal card -->
                        <div
                            v-if="block.homeworkCard"
                            class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800"
                        >
                            📚 {{ $t('board.homework') }} · {{ block.homeworkCard.start }}<span v-if="block.homeworkCard.end">–{{ block.homeworkCard.end }}</span> {{ $t('common.oclock') }}
                        </div>

                        <template v-else>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink/40">
                            {{ block.time ?? $t('board.no_fixed_time') }}<span v-if="block.time"> {{ $t('common.oclock') }}</span>
                        </p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div
                                v-for="row in block.rows"
                                :key="row.id"
                                class="rounded-2xl bg-surface p-4 shadow-sm transition"
                                :class="[
                                    { 'opacity-60': row.status === 'picked_up' || row.status === 'sent_home' },
                                    homeworkConflict(row) ? 'ring-1 ring-amber-300' : '',
                                ]"
                            >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">
                                {{ row.name }}
                                <span
                                    v-if="row.birthday !== null"
                                    class="ml-1 rounded-md bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700"
                                >
                                    {{ $t('board.turns', { age: row.birthday }) }}
                                </span>
                            </p>
                            <p class="mt-0.5 text-sm text-ink/60">
                                {{ planLabel(row) }}
                                <span v-if="row.comment" class="text-ink/45">
                                    · {{ row.comment }}
                                </span>
                                <span
                                    v-if="row.is_overridden"
                                    class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-700"
                                >
                                    {{ $t('board.changed_today') }}
                                </span>
                            </p>
                            <p
                                v-if="row.excursion"
                                class="mt-1 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold"
                                :class="row.excursion.state === 'back'
                                    ? 'bg-hort-teal/20 text-hort-teal-dark'
                                    : 'bg-hort-purple/15 text-hort-purple'"
                            >
                                <template v-if="row.excursion.state === 'away'">
                                    {{ $t('board.on_excursion', { name: row.excursion.name }) }}
                                    <span v-if="row.excursion.return_at" class="font-medium">
                                        {{ $t('board.back_approx', { time: row.excursion.return_at }) }}
                                    </span>
                                </template>
                                <template v-else-if="row.excursion.state === 'back'">
                                    {{ $t('board.back_from', { name: row.excursion.name }) }}
                                </template>
                                <template v-else>
                                    {{ $t('board.excursion_label', { name: row.excursion.name }) }}
                                    <span v-if="row.excursion.return_at" class="font-medium">
                                        {{ $t('board.back_at_time', { time: row.excursion.return_at }) }}
                                    </span>
                                </template>
                            </p>
                            <p
                                v-if="row.status === 'present' && excursionConflict(row)"
                                class="mt-1 inline-block rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                            >
                                {{ $t('board.pickup_during_excursion') }}
                            </p>
                            <p
                                v-if="row.status === 'present' && homeworkConflict(row)"
                                class="mt-1 inline-block rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                            >
                                {{ $t('board.pickup_during_homework') }}
                            </p>
                        </div>

                        <!-- Status badge once the child has left -->
                        <div
                            v-if="row.status === 'picked_up' || row.status === 'sent_home'"
                            class="shrink-0 text-right"
                        >
                            <p
                                class="text-sm font-semibold"
                                :class="row.status === 'sent_home' ? 'text-hort-orange-dark' : 'text-hort-teal-dark'"
                            >
                                <span v-if="row.status === 'sent_home'">🚶 </span>✓ {{ row.status_label }}
                            </p>
                            <p class="text-xs text-ink/40">
                                {{ row.left_at }}<span v-if="row.marked_by"> · {{ row.marked_by }}</span>
                            </p>
                        </div>
                    </div>


                    <!-- Actions -->
                    <div
                        v-if="row.status === 'present'"
                        class="mt-3 space-y-2"
                    >
                            <p
                                v-if="row.excursion?.state === 'away'"
                                class="rounded-xl bg-hort-purple/10 py-2.5 text-center text-sm font-medium text-hort-purple"
                            >
                                {{ $t('board.on_excursion_wait') }}
                            </p>
                            <div
                                v-else-if="canMark"
                                class="grid grid-cols-2 gap-2"
                            >
                                <button
                                    type="button"
                                    :disabled="submitting"
                                    class="rounded-xl bg-hort-teal py-3 font-semibold text-hort-navy transition hover:bg-hort-teal-dark active:scale-[0.98] disabled:opacity-50"
                                    @click="mark(row, 'picked_up')"
                                >
                                    {{ $t('board.picked_up_button') }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="submitting"
                                    class="rounded-xl bg-hort-orange py-3 font-semibold text-hort-navy transition hover:opacity-90 active:scale-[0.98] disabled:opacity-50"
                                    @click="mark(row, 'sent_home')"
                                >
                                    {{ $t('board.sent_home_button') }}
                                </button>
                            </div>
                            <button
                                v-if="row.can_override && row.excursion?.state !== 'away'"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border-2 border-ink/10 px-3 py-2 text-sm font-semibold text-ink transition hover:border-hort-teal hover:bg-hort-teal/10 active:scale-[0.98]"
                                @click="editRow(row)"
                            >
                                <PencilSquareIcon class="h-4 w-4" />
                                {{ $t('board.change_pickup_time') }}
                            </button>

                            <div
                                v-if="row.can_override && row.excursion?.state !== 'away'"
                                class="text-sm"
                            >
                                <div v-if="absenceRow !== row.id" class="flex items-center gap-2">
                                    <span class="text-ink/40">{{ $t('board.not_here') }}</span>
                                    <button
                                        type="button"
                                        class="font-semibold text-amber-700 underline-offset-2 hover:underline"
                                        @click="stageAbsence(row, 'sick')"
                                    >
                                        {{ $t('board.report_sick') }}
                                    </button>
                                    <span class="text-ink/20">·</span>
                                    <button
                                        type="button"
                                        class="font-semibold text-amber-700 underline-offset-2 hover:underline"
                                        @click="stageAbsence(row, 'away')"
                                    >
                                        {{ $t('board.report_away') }}
                                    </button>
                                </div>
                                <form
                                    v-else
                                    class="space-y-2 rounded-xl bg-amber-50 p-3"
                                    @submit.prevent="submitAbsence(row)"
                                >
                                    <label class="block font-medium text-amber-800">
                                        {{ absenceReason === 'sick' ? $t('board.report_sick') : $t('board.report_away') }} · {{ $t('weekly.reason_label') }}
                                    </label>
                                    <input
                                        v-model="absenceComment"
                                        type="text"
                                        maxlength="255"
                                        :placeholder="$t('weekly.reason_placeholder')"
                                        class="w-full rounded-lg border-amber-200 bg-surface text-sm text-ink focus:border-amber-400 focus:ring-amber-400"
                                    />
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="submit"
                                            :disabled="!absenceComment.trim() || absenceSaving"
                                            class="rounded-lg bg-amber-600 px-3 py-1.5 font-semibold text-white transition hover:bg-amber-700 disabled:opacity-40"
                                        >
                                            {{ $t('board.report_button') }}
                                        </button>
                                        <button
                                            type="button"
                                            class="text-ink/50 underline-offset-2 hover:underline"
                                            @click="cancelAbsence"
                                        >
                                            {{ $t('common.cancel') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div v-else-if="canMark" class="mt-3">
                            <button
                                type="button"
                                class="text-sm font-medium text-ink/50 underline-offset-2 hover:underline"
                                @click="mark(row, 'present')"
                            >
                                {{ $t('common.undo') }}
                            </button>
                        </div>
                            </div>
                        </div>
                        </template>
                    </div>
                </template>
            </div>

            <p
                v-else
                class="rounded-2xl border-2 border-dashed border-ink/15 p-6 text-center text-sm text-ink/50"
            >
                <template v-if="rows.length && isParent && !showAll">
                    {{ $t('board.empty_own') }}
                </template>
                <template v-else>
                    {{ $t('board.empty_all') }}
                </template>
            </p>
        </div>

        <DayEditor
            ref="dayEditor"
            :children="children"
            :method-options="methodOptions"
            :qualifier-options="qualifierOptions"
        />
    </AuthenticatedLayout>
</template>
