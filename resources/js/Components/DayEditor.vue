<script setup>
// The shared day-editor popup — one child on one date. Used by the Wochenplan grid
// and the Heute board (pill + „Abholzeit ändern"), so editing a pickup is the exact
// same dialog everywhere: Krank/Kommt nicht, Uhrzeit, Art (incl. „geht mit … mit"),
// bis/genau um/ab, Kommentar, „Auf Standard". All roads post to weekly-plan.adjust /
// absences — the caller just opens it via `open(child, day, dayMeta)`.
import { adjust as weeklyPlanAdjust, reset as weeklyPlanReset } from '@/routes/weekly-plan';
import { store as absenceStore, destroy as absenceDestroy } from '@/routes/absences';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TimeSelect from '@/Components/TimeSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { t } from '@/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    // Companion picker source: [{ id, name, times: { 'YYYY-MM-DD': 'HH:MM' } }].
    children: { type: Array, default: () => [] },
    methodOptions: { type: Array, default: () => [] },
    qualifierOptions: { type: Array, default: () => [] },
    // What's running on which day, Hort-wide: [{ date, kind: 'homework'|'activity',
    // label, start, end }]. A pickup inside one of these is worth saying out loud.
    windows: { type: Array, default: () => [] },
});

const editing = ref(null); // { childId, childName, date, label, absent }
const form = reactive({ planned_time: '', planned_method: '', time_qualifier: 'at', companion_child_id: '', note: '', absence_reason: '', arrives_at: '', arrival_note: '' });
// „Kommt später" is optional extra info — folded away until asked for (or already set).
const showArrival = ref(false);
const saveError = ref('');
const saving = ref(false);

function open(child, day, dayMeta) {
    if (!day.editable) {
        return;
    }
    editing.value = {
        childId: child.id,
        childName: child.name,
        date: day.date,
        label: `${dayMeta.label} ${dayMeta.date_label}`.trim(),
        absent: day.absent ?? null,
        // Ferienbetreuung day: there is no Stammplan behind it (see the reset button).
        care: day.care ?? null,
        // The trip this child is on that day, if any — a pickup inside it can't happen
        // at the Hort, so the time field says so (saving stays possible: a family may
        // collect the child at the venue).
        excursion: day.excursion ?? null,
    };
    form.planned_time = day.time ?? '';
    form.planned_method = day.method ?? '';
    form.time_qualifier = day.qualifier ?? 'at';
    form.companion_child_id = day.companion?.id ?? '';
    form.note = day.note ?? '';
    form.absence_reason = '';
    form.arrives_at = day.arrives_at ?? '';
    form.arrival_note = day.arrival_note ?? '';
    showArrival.value = !!day.arrives_at;
    saveError.value = '';
}

function removeArrival() {
    form.arrives_at = '';
    form.arrival_note = '';
    showArrival.value = false;
}

/**
 * Everything the chosen pickup time runs into on that day: the Hausaufgaben slot, a
 * timed Aktivität, and the child's Ausflug. Warnings only — a family may well collect
 * their child mid-activity; they just shouldn't find out afterwards.
 */
const pickupClashes = computed(() => {
    const time = form.planned_time;
    if (!editing.value || !time || goingWithChild.value) {
        return [];
    }

    const clashes = props.windows
        .filter((w) => w.date === editing.value.date && time >= w.start && time < w.end)
        .map((w) => ({
            key: w.kind,
            text: t(`weekly.pickup_in_${w.kind}`, { time, name: w.label, from: w.start, to: w.end }),
        }));

    const trip = editing.value.excursion;
    if (trip?.return_at && time >= (trip.depart_at ?? '00:00') && time < trip.return_at) {
        clashes.push({
            key: 'excursion',
            text: t('weekly.pickup_in_excursion', { name: trip.name, time: trip.return_at }),
        });
    }

    return clashes;
});

// Arriving at or after the pickup time can't happen (mirrors AdjustDayRequest). With a
// companion the pickup is mirrored from them, so that time is the one to beat.
const arrivalAfterPickup = computed(() => {
    if (!form.arrives_at) {
        return false;
    }
    const pickup = goingWithChild.value ? selectedCompanionTime.value : form.planned_time;

    return !!pickup && form.arrives_at >= pickup;
});

function close() {
    editing.value = null;
}

defineExpose({ open });

function showFirstError(errors) {
    saveError.value = errors.companion_child_id || errors.planned_time || errors.arrives_at || Object.values(errors)[0] || '';
}

const stagingAbsence = computed(() => form.absence_reason !== '');
const goingWithChild = computed(() => form.planned_method === 'with_child');

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
const selectedCompanion = computed(() => companionChoices.value.find((c) => c.id === form.companion_child_id));
const selectedCompanionName = computed(() => selectedCompanion.value?.name ?? '');
const selectedCompanionTime = computed(() => selectedCompanion.value?.time ?? '');
const selectedCompanionUnavailable = computed(() => !!selectedCompanion.value && !selectedCompanion.value.available);

const page = usePage();
const lateChangeCutoff = computed(() => page.props.lateChangeCutoff ?? '12:00');

/**
 * Whether saving this edit will DM the staff: a parent changing *today* after the
 * Hort-wide cutoff. Mirrors App\Support\LateChange::applies() — the server decides,
 * this only warns beforehand. Recomputed on open (`editing` is a dependency).
 */
const notifiesStaff = computed(() => {
    if (!editing.value || page.props.auth?.user?.role === 'staff') {
        return false;
    }
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    if (editing.value.date !== today) {
        return false;
    }
    const time = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    return time >= lateChangeCutoff.value;
});

const canSave = computed(() => {
    if (stagingAbsence.value) {
        return !!form.note.trim();
    }
    if (arrivalAfterPickup.value) {
        return false;
    }
    if (goingWithChild.value) {
        // The time is mirrored from the companion, so only a valid companion is needed.
        return !!form.companion_child_id && !selectedCompanionUnavailable.value;
    }
    // A real pickup needs both a method and a time — no half-set (offen / „— —") plans.
    return !!form.planned_method && !!form.planned_time;
});

function save() {
    if (saving.value || !canSave.value) {
        return;
    }
    saveError.value = '';
    const opts = {
        preserveScroll: true,
        onStart: () => { saving.value = true; },
        onFinish: () => { saving.value = false; },
        onSuccess: close,
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
            arrives_at: form.arrives_at || null,
            arrival_note: form.arrives_at ? form.arrival_note || null : null,
        },
        opts,
    );
}

function resetDay() {
    router.patch(
        weeklyPlanReset().url,
        { child_id: editing.value.childId, date: editing.value.date },
        { preserveScroll: true, onSuccess: close },
    );
}

// Stage/unstage a fresh absence locally — committed only on Speichern (see save()).
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
        data: { child_id: editing.value.childId, from: editing.value.date, to: editing.value.date },
        preserveScroll: true,
        onSuccess: close,
    });
}
</script>

<template>
    <Modal :show="editing !== null" max-width="sm" @close="close">
        <div v-if="editing" class="space-y-5 p-6">
            <div>
                <h2 class="text-lg font-semibold text-ink">{{ editing.childName }}</h2>
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
                    <p class="text-sm text-ink/60">{{ $t('weekly.not_here') }}</p>
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

            <!-- „Kommt später": optional, only for this day — no arrival gets marked.
                 Right under Krank/Kommt nicht: the other „not here as usual" answer. -->
            <fieldset
                :disabled="!!editing.absent || stagingAbsence"
                :class="editing.absent || stagingAbsence ? 'opacity-40' : ''"
            >
                <button
                    v-if="!showArrival"
                    type="button"
                    data-testid="arrival-toggle"
                    class="text-sm font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                    @click="showArrival = true"
                >
                    + {{ $t('weekly.arrival_add') }}
                </button>
                <div v-else class="space-y-3 rounded-lg bg-hort-blue/10 p-3" data-testid="arrival-section">
                    <div class="flex items-center justify-between">
                        <InputLabel for="arrives-at" :value="$t('weekly.arrival_label')" />
                        <button
                            type="button"
                            class="text-xs font-medium text-ink/50 underline-offset-2 hover:underline"
                            @click="removeArrival"
                        >
                            {{ $t('weekly.arrival_remove') }}
                        </button>
                    </div>
                    <TimeSelect id="arrives-at" v-model="form.arrives_at" test-id="arrives-at" class="block w-full" />
                    <p v-if="arrivalAfterPickup" class="text-xs font-medium text-red-700">
                        {{ $t('weekly.arrival_after_pickup') }}
                    </p>
                    <div>
                        <InputLabel for="arrival-note" :value="$t('weekly.arrival_note_label')" />
                        <TextInput
                            id="arrival-note"
                            v-model="form.arrival_note"
                            data-testid="arrival-note"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full"
                            :placeholder="$t('weekly.arrival_note_placeholder')"
                        />
                    </div>
                </div>
            </fieldset>

            <!-- Pickup plan — disabled while the child is (or is being) reported away -->
            <fieldset
                :disabled="!!editing.absent || stagingAbsence"
                class="space-y-5"
                :class="editing.absent || stagingAbsence ? 'opacity-40' : ''"
            >
                <div v-if="!goingWithChild">
                    <InputLabel for="time" :value="$t('weekly.time_label')" />
                    <TimeSelect id="time" v-model="form.planned_time" test-id="time" class="mt-1 block w-full" />
                    <!-- Everything the chosen time runs into: Hausaufgaben, a timed
                         Aktivität, the child's Ausflug. Saving stays possible. -->
                    <p
                        v-for="clash in pickupClashes"
                        :key="clash.key"
                        :data-testid="`${clash.key}-clash`"
                        class="mt-1 rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-900"
                    >
                        ⚠️ {{ clash.text }}
                    </p>
                </div>

                <div>
                    <InputLabel for="method" :value="$t('weekly.method_label')" />
                    <select
                        id="method"
                        v-model="form.planned_method"
                        data-testid="method"
                        class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal disabled:bg-ink/5 disabled:text-ink/40"
                    >
                        <option value="">{{ $t('weekly.method_open') }}</option>
                        <option v-for="o in methodOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>

                <!-- „Geht mit … mit": pick the companion; the time is taken from them. -->
                <div v-if="goingWithChild">
                    <InputLabel for="companion" :value="$t('weekly.companion_label')" />
                    <select
                        id="companion"
                        data-testid="companion"
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
                    <p v-if="!selectedCompanionName" class="mt-1 text-xs text-ink/50">
                        {{ $t('weekly.companion_empty_hint') }}
                    </p>
                    <template v-else>
                        <p v-if="selectedCompanionUnavailable" class="mt-1 text-xs font-medium text-red-700">
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
                        <option v-for="o in qualifierOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
            </fieldset>

            <div>
                <InputLabel for="note" :value="stagingAbsence ? $t('weekly.reason_label') : $t('common.note')" />
                <TextInput
                    id="note"
                    v-model="form.note"
                    type="text"
                    maxlength="255"
                    class="mt-1 block w-full"
                    :placeholder="stagingAbsence ? $t('weekly.reason_placeholder') : $t('weekly.note_placeholder')"
                />
                <!-- No hint while reporting an absence: the amber line above already
                     asks for the reason, and the label says „Pflicht". -->
                <p v-if="!stagingAbsence" class="mt-1 text-xs text-ink/50">
                    {{ $t('weekly.note_hint') }}
                </p>
            </div>

            <p
                v-if="notifiesStaff"
                data-testid="late-change-hint"
                class="rounded-lg bg-hort-orange/10 px-3 py-2 text-sm text-hort-orange-dark"
            >
                {{ $t('weekly.late_change_hint', { time: lateChangeCutoff }) }}
            </p>

            <p v-if="saveError" class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
                {{ saveError }}
            </p>

            <div class="flex items-center justify-between gap-3 pt-2">
                <!-- A Ferienbetreuung day has no Stammplan to return to: „zurücksetzen"
                     would cancel the child's place, which belongs on /care where the
                     Anmeldeschluss applies. -->
                <button
                    v-if="!editing.care"
                    type="button"
                    data-testid="reset"
                    class="text-sm font-medium text-ink/50 underline-offset-2 hover:underline"
                    @click="resetDay"
                >
                    {{ $t('weekly.reset_to_standard') }}
                </button>
                <div class="flex gap-3">
                    <SecondaryButton @click="close">{{ $t('common.cancel') }}</SecondaryButton>
                    <PrimaryButton data-testid="save" :disabled="!canSave || saving" @click="save">{{ saving ? $t('common.saving') : $t('common.save') }}</PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
