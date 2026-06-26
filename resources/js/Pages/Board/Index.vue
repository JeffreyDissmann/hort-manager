<script setup>
import { mark as boardMark, override as boardOverride } from '@/routes/board';
import { live as excursionLive } from '@/routes/excursions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TimeSelect from '@/Components/TimeSelect.vue';
import { PencilSquareIcon } from '@heroicons/vue/24/outline';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    date: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    excursions: { type: Array, default: () => [] },
    program: { type: Object, default: null },
    canMark: { type: Boolean, default: false },
    methodOptions: { type: Array, default: () => [] },
});

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

function planLabel(row) {
    const method = methodLabels.value[row.planned_method];
    return method ? `${row.planned_time} · ${method}` : row.planned_time;
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

function mark(row, status) {
    router.patch(
        boardMark(row.id).url,
        { status },
        { preserveScroll: true },
    );
}

function liveEvent(excursion, event) {
    router.patch(
        excursionLive(excursion.id).url,
        { event },
        { preserveScroll: true },
    );
}

// --- Same-day override (inline editor) ---
const editingId = ref(null);
const editTime = ref('');
const editMethod = ref('');
const editNote = ref('');

function openEdit(row) {
    editingId.value = row.id;
    editTime.value = row.planned_time ?? '';
    editMethod.value = row.planned_method ?? '';
    editNote.value = row.note ?? '';
}

function cancelEdit() {
    editingId.value = null;
}

function saveEdit(row) {
    router.patch(
        boardOverride(row.id).url,
        {
            planned_time: editTime.value,
            planned_method: editMethod.value || null,
            note: editNote.value || null,
        },
        { preserveScroll: true, onSuccess: () => (editingId.value = null) },
    );
}
</script>

<template>
    <Head title="Tagesboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-hort-navy">
                    {{ date.is_today ? 'Heute' : 'Nächster Hort-Tag' }}
                </h2>
                <p class="text-sm text-hort-navy/50">{{ date.label }}</p>
            </div>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <!-- Today's program (lunch + activity) -->
            <div
                v-if="program"
                class="rounded-2xl bg-white p-4 shadow-sm"
            >
                <p v-if="program.lunch" class="text-sm text-hort-navy">
                    <span class="font-semibold">Mittagessen:</span>
                    {{ program.lunch }}
                </p>
                <p
                    v-if="program.activity"
                    class="text-sm text-hort-navy"
                    :class="program.lunch ? 'mt-1' : ''"
                >
                    <span class="font-semibold">Aktivität:</span>
                    {{ program.activity }}
                </p>
                <p
                    v-if="program.homework_start"
                    class="text-sm text-hort-navy"
                    :class="program.lunch || program.activity ? 'mt-1' : ''"
                >
                    <span class="font-semibold">Hausaufgaben:</span>
                    {{ program.homework_start }}<span v-if="program.homework_end"
                        >–{{ program.homework_end }}</span
                    >
                    Uhr
                </p>
            </div>

            <!-- Summary (left) + parent filter (right), one line -->
            <div class="flex flex-wrap items-center gap-2">
                <div
                    v-if="rows.length"
                    class="flex gap-2 text-sm font-semibold"
                >
                    <span class="rounded-xl bg-white px-3 py-2 text-hort-navy shadow-sm">
                        {{ counts.present }} noch da
                    </span>
                    <span class="rounded-xl bg-white px-3 py-2 text-hort-navy/50 shadow-sm">
                        {{ counts.left }} weg
                    </span>
                    <span
                        v-if="counts.excursion"
                        class="rounded-xl bg-white px-3 py-2 text-hort-purple shadow-sm"
                    >
                        {{ counts.excursion }} Ausflug
                    </span>
                </div>

                <div
                    v-if="isParent"
                    class="ml-auto inline-flex rounded-lg bg-white p-0.5 text-xs font-semibold shadow-sm"
                >
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 transition"
                        :class="showAll ? 'bg-hort-teal text-hort-navy' : 'text-hort-navy/50'"
                        @click="showAll = true"
                    >
                        Alle
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 transition"
                        :class="!showAll ? 'bg-hort-teal text-hort-navy' : 'text-hort-navy/50'"
                        @click="showAll = false"
                    >
                        Nur meine
                    </button>
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
                            <p class="mt-0.5 text-sm text-hort-navy/60">
                                {{ ex.child_count }} Kinder<span
                                    v-if="ex.depart_at"
                                >
                                    · {{ ex.depart_at }}<span v-if="ex.return_at"
                                        >–{{ ex.return_at }}</span
                                    >
                                    Uhr</span
                                >
                            </p>
                            <p
                                v-if="ex.state === 'away'"
                                class="mt-1 text-sm font-medium text-hort-purple"
                            >
                                Unterwegs seit {{ ex.departed_at }}
                            </p>
                            <p
                                v-else-if="ex.state === 'back'"
                                class="mt-1 text-sm font-medium text-hort-teal-dark"
                            >
                                ✓ Zurück um {{ ex.returned_at }}
                            </p>
                        </div>

                        <div v-if="canMark" class="shrink-0">
                            <button
                                v-if="ex.state === 'planned'"
                                type="button"
                                class="rounded-xl bg-hort-purple px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 active:scale-[0.98]"
                                @click="liveEvent(ex, 'depart')"
                            >
                                Losgegangen
                            </button>
                            <button
                                v-else-if="ex.state === 'away'"
                                type="button"
                                class="rounded-xl bg-hort-teal px-4 py-2 text-sm font-semibold text-hort-navy transition hover:bg-hort-teal-dark active:scale-[0.98]"
                                @click="liveEvent(ex, 'return')"
                            >
                                Zurück
                            </button>
                        </div>
                    </div>

                    <div v-if="canMark && ex.state !== 'planned'" class="mt-2 text-right">
                        <button
                            type="button"
                            class="text-xs font-medium text-hort-navy/40 underline-offset-2 hover:underline"
                            @click="liveEvent(ex, ex.state === 'back' ? 'undo_return' : 'undo_depart')"
                        >
                            Rückgängig
                        </button>
                    </div>
                </div>
            </div>

            <ul v-if="visibleRows.length" class="space-y-3">
                <li
                    v-for="row in visibleRows"
                    :key="row.id"
                    class="rounded-2xl bg-white p-4 shadow-sm transition"
                    :class="{ 'opacity-60': row.status === 'picked_up' || row.status === 'sent_home' }"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-hort-navy">
                                {{ row.name }}
                                <span
                                    v-if="row.birthday !== null"
                                    class="ml-1 rounded-md bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700"
                                >
                                    🎂 wird {{ row.birthday }}
                                </span>
                            </p>
                            <p class="mt-0.5 text-sm text-hort-navy/60">
                                {{ planLabel(row) }}
                                <span v-if="row.comment" class="text-hort-navy/45">
                                    · {{ row.comment }}
                                </span>
                                <span
                                    v-if="row.is_overridden"
                                    class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-700"
                                >
                                    heute geändert
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
                                    🚌 Unterwegs: {{ row.excursion.name }}
                                    <span v-if="row.excursion.return_at" class="font-medium">
                                        · zurück ~{{ row.excursion.return_at }}
                                    </span>
                                </template>
                                <template v-else-if="row.excursion.state === 'back'">
                                    ✓ Zurück vom {{ row.excursion.name }}
                                </template>
                                <template v-else>
                                    🚌 Ausflug: {{ row.excursion.name }}
                                    <span v-if="row.excursion.return_at" class="font-medium">
                                        · zurück {{ row.excursion.return_at }}
                                    </span>
                                </template>
                            </p>
                            <p
                                v-if="row.status === 'present' && excursionConflict(row)"
                                class="mt-1 inline-block rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                            >
                                Abholung liegt im Ausflug
                            </p>
                            <p
                                v-if="row.status === 'present' && homeworkConflict(row)"
                                class="mt-1 inline-block rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                            >
                                Abholung liegt in der Hausaufgabenzeit
                            </p>
                        </div>

                        <!-- Status badge once the child has left -->
                        <div
                            v-if="row.status === 'picked_up' || row.status === 'sent_home'"
                            class="shrink-0 text-right"
                        >
                            <p
                                class="text-sm font-semibold"
                                :class="row.status === 'sent_home' ? 'text-hort-purple' : 'text-hort-teal-dark'"
                            >
                                ✓ {{ row.status_label }}
                            </p>
                            <p class="text-xs text-hort-navy/40">
                                {{ row.left_at }}<span v-if="row.marked_by"> · {{ row.marked_by }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Inline same-day override editor -->
                    <div
                        v-if="editingId === row.id"
                        class="mt-3 space-y-3 rounded-xl bg-hort-sand p-3"
                    >
                        <TimeSelect v-model="editTime" class="text-sm" />
                        <select
                            v-model="editMethod"
                            class="block w-full rounded-lg border-gray-300 text-sm focus:border-hort-teal focus:ring-hort-teal"
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
                        <input
                            v-model="editNote"
                            type="text"
                            maxlength="255"
                            placeholder="Kommentar, z. B. wegen Arzttermin"
                            class="block w-full rounded-lg border-gray-300 text-sm focus:border-hort-teal focus:ring-hort-teal"
                        />
                        <div class="flex justify-end gap-3 text-sm">
                            <button
                                type="button"
                                class="px-2 py-1 text-hort-navy/60"
                                @click="cancelEdit"
                            >
                                Abbrechen
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-hort-navy px-4 py-1.5 font-semibold text-white"
                                @click="saveEdit(row)"
                            >
                                Speichern
                            </button>
                        </div>
                    </div>

                    <!-- Actions -->
                    <template v-else>
                        <div
                            v-if="row.status === 'present'"
                            class="mt-3 space-y-2"
                        >
                            <p
                                v-if="row.excursion?.state === 'away'"
                                class="rounded-xl bg-hort-purple/10 py-2.5 text-center text-sm font-medium text-hort-purple"
                            >
                                Auf Ausflug – Abholung erst nach der Rückkehr
                            </p>
                            <div
                                v-else-if="canMark"
                                class="grid grid-cols-2 gap-2"
                            >
                                <button
                                    type="button"
                                    class="rounded-xl bg-hort-teal py-3 font-semibold text-hort-navy transition hover:bg-hort-teal-dark active:scale-[0.98]"
                                    @click="mark(row, 'picked_up')"
                                >
                                    Abgeholt
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl bg-hort-purple py-3 font-semibold text-white transition hover:opacity-90 active:scale-[0.98]"
                                    @click="mark(row, 'sent_home')"
                                >
                                    Nach Hause
                                </button>
                            </div>
                            <button
                                v-if="row.can_override && row.excursion?.state !== 'away'"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border-2 border-hort-navy/10 px-3 py-2 text-sm font-semibold text-hort-navy transition hover:border-hort-teal hover:bg-hort-teal/10 active:scale-[0.98]"
                                @click="openEdit(row)"
                            >
                                <PencilSquareIcon class="h-4 w-4" />
                                Abholzeit ändern
                            </button>
                        </div>

                        <div v-else-if="canMark" class="mt-3">
                            <button
                                type="button"
                                class="text-sm font-medium text-hort-navy/50 underline-offset-2 hover:underline"
                                @click="mark(row, 'present')"
                            >
                                Rückgängig
                            </button>
                        </div>
                    </template>
                </li>
            </ul>

            <p
                v-else
                class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
            >
                <template v-if="rows.length && isParent && !showAll">
                    Heute ist keins deiner Kinder im Hort.
                </template>
                <template v-else>
                    Für diesen Tag sind keine Kinder im Hort.
                </template>
            </p>
        </div>
    </AuthenticatedLayout>
</template>
