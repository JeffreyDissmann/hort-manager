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
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    // Companion picker source: [{ id, name, times: { 'YYYY-MM-DD': 'HH:MM' } }].
    children: { type: Array, default: () => [] },
    methodOptions: { type: Array, default: () => [] },
    qualifierOptions: { type: Array, default: () => [] },
});

const editing = ref(null); // { childId, childName, date, label, absent }
const form = reactive({ planned_time: '', planned_method: '', time_qualifier: 'at', companion_child_id: '', note: '', absence_reason: '' });
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
    };
    form.planned_time = day.time ?? '';
    form.planned_method = day.method ?? '';
    form.time_qualifier = day.qualifier ?? 'at';
    form.companion_child_id = day.companion?.id ?? '';
    form.note = day.note ?? '';
    form.absence_reason = '';
    saveError.value = '';
}

function close() {
    editing.value = null;
}

defineExpose({ open });

function showFirstError(errors) {
    saveError.value = errors.companion_child_id || errors.planned_time || Object.values(errors)[0] || '';
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

const canSave = computed(() => {
    if (stagingAbsence.value) {
        return !!form.note.trim();
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
                <div v-if="!goingWithChild">
                    <InputLabel for="time" :value="$t('weekly.time_label')" />
                    <TimeSelect id="time" v-model="form.planned_time" class="mt-1 block w-full" />
                </div>

                <div>
                    <InputLabel for="method" :value="$t('weekly.method_label')" />
                    <select
                        id="method"
                        v-model="form.planned_method"
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
                <p class="mt-1 text-xs" :class="stagingAbsence ? 'font-medium text-hort-orange-dark' : 'text-ink/50'">
                    {{ stagingAbsence ? $t('weekly.reason_hint') : $t('weekly.note_hint') }}
                </p>
            </div>

            <p v-if="saveError" class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
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
                    <SecondaryButton @click="close">{{ $t('common.cancel') }}</SecondaryButton>
                    <PrimaryButton :disabled="!canSave || saving" @click="save">{{ saving ? $t('common.saving') : $t('common.save') }}</PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
