<script setup>
// Ferien: the two Hort-wide period types. „Geschlossen" means no Hort at all;
// „Ferienbetreuung" offers days children opt into. Staff manage both here; parents
// read the same page to plan around them.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CareDayRow from '@/Components/CareDayRow.vue';
import DatePicker from '@/Components/DatePicker.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { store as closuresStore, update as closuresUpdate, destroy as closuresDestroy } from '@/routes/closures';
import { restore as careDayRestore } from '@/routes/care-days';
import { program as programRoute } from '@/routes';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    upcoming: { type: Array, default: () => [] },
    past: { type: Array, default: () => [] },
    // Ferienbetreuung periods, each with its offered days.
    care: { type: Array, default: () => [] },
    careDefaults: { type: Object, default: () => ({ starts_at: '08:30', ends_at: '16:30' }) },
    canManage: { type: Boolean, default: false },
});

const flash = computed(() => usePage().props.flash?.status);
const locale = computed(() => usePage().props.locale || 'de');

const form = useForm({
    name: '',
    type: 'closed',
    starts_on: '',
    ends_on: '',
    registration_deadline: '',
    note: '',
});

const isCare = computed(() => form.type === 'care');
const editingId = ref(null);
const showPast = ref(false);
const confirmingDelete = ref(null);

// „Von" is the anchor: picking it forwards an empty or earlier „bis" along, so a
// single closed day (the common Brückentag case) needs just one click.
function onStartPicked(value) {
    form.starts_on = value;
    if (!form.ends_on || form.ends_on < value) {
        form.ends_on = value;
    }
}

function edit(period, type = 'closed') {
    editingId.value = period.id;
    form.clearErrors();
    form.name = period.name;
    form.type = type;
    form.starts_on = period.starts_on;
    form.ends_on = period.ends_on;
    form.registration_deadline = period.registration_deadline ?? '';
    form.note = period.note ?? '';
}

function cancel() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: cancel,
        // A closure has nothing to register for.
        transform: (data) => ({
            ...data,
            registration_deadline: data.type === 'care' ? data.registration_deadline || null : null,
        }),
    };

    if (editingId.value) {
        form.transform(options.transform).patch(closuresUpdate(editingId.value).url, options);
    } else {
        form.transform(options.transform).post(closuresStore().url, options);
    }
}

function remove(period) {
    router.delete(closuresDestroy(period.id).url, {
        preserveScroll: true,
        onFinish: () => (confirmingDelete.value = null),
    });
}

/** „31. Juli 2026" — a single date, for the registration deadline. */
function dateLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** „Mi, 9. Sep." — the same short form the offered-day rows use. */
function dayLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

/** Put a removed day back on the sign-up sheet (its sign-ups are not restored). */
function restoreDay(day) {
    router.patch(careDayRestore(day.id).url, {}, { preserveScroll: true });
}

/** „12.–16. August 2026", or a single day when the period is one day long. */
function rangeLabel(period) {
    const from = new Date(`${period.starts_on}T00:00:00`);
    const to = new Date(`${period.ends_on}T00:00:00`);
    const long = { day: 'numeric', month: 'long', year: 'numeric' };

    if (period.starts_on === period.ends_on) {
        return from.toLocaleDateString(locale.value, { weekday: 'long', ...long });
    }

    return `${from.toLocaleDateString(locale.value, { day: 'numeric', month: 'long' })} – ${to.toLocaleDateString(locale.value, long)}`;
}
</script>

<template>
    <Head :title="$t('closures.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('closures.header') }}</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <p class="text-sm text-ink/60">{{ $t('closures.intro') }}</p>

            <!-- Add / edit (staff only) -->
            <div v-if="canManage" class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="mb-3 font-semibold text-ink">
                    {{ editingId ? $t('closures.edit_heading') : $t('closures.add_heading') }}
                </p>

                <!-- Which kind: no Hort at all, or opt-in care. Only when creating —
                     converting an existing Ferienbetreuung would throw away every
                     sign-up, so the type is fixed once it exists. -->
                <div v-if="!editingId" class="mb-3 inline-flex rounded-lg bg-canvas p-0.5 text-sm font-semibold">
                    <button
                        type="button"
                        data-testid="type-closed"
                        class="rounded-md px-3 py-1 transition"
                        :class="!isCare ? 'bg-surface text-ink shadow-sm' : 'text-ink/50 hover:text-ink'"
                        @click="form.type = 'closed'"
                    >
                        {{ $t('enums.holiday_period_type.closed') }}
                    </button>
                    <button
                        type="button"
                        data-testid="type-care"
                        class="rounded-md px-3 py-1 transition"
                        :class="isCare ? 'bg-surface text-ink shadow-sm' : 'text-ink/50 hover:text-ink'"
                        @click="form.type = 'care'"
                    >
                        {{ $t('enums.holiday_period_type.care') }}
                    </button>
                </div>

                <p v-else class="mb-3 text-sm font-medium text-ink/60">
                    {{ $t(`enums.holiday_period_type.${form.type}`) }}
                </p>

                <div class="grid gap-3 lg:grid-cols-[minmax(0,16rem),minmax(0,12rem),minmax(0,12rem),minmax(0,1fr)]">
                    <div>
                        <InputLabel for="closure-name" :value="$t('closures.name')" />
                        <TextInput
                            id="closure-name"
                            v-model="form.name"
                            type="text"
                            maxlength="255"
                            data-testid="closure-name"
                            class="mt-1 block w-full"
                            :placeholder="$t('closures.name_placeholder')"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <InputLabel for="closure-from" :value="$t('closures.from')" />
                        <DatePicker
                            id="closure-from"
                            :model-value="form.starts_on"
                            class="mt-1"
                            @update:model-value="onStartPicked"
                        />
                        <p v-if="form.errors.starts_on" class="mt-1 text-xs text-red-600">{{ form.errors.starts_on }}</p>
                    </div>

                    <div>
                        <InputLabel for="closure-to" :value="$t('closures.to')" />
                        <DatePicker
                            id="closure-to"
                            v-model="form.ends_on"
                            :min="form.starts_on"
                            class="mt-1"
                        />
                        <p v-if="form.errors.ends_on" class="mt-1 text-xs text-red-600">{{ form.errors.ends_on }}</p>
                    </div>

                    <div v-if="isCare">
                        <InputLabel for="care-deadline" :value="$t('care.deadline')" />
                        <DatePicker
                            id="care-deadline"
                            v-model="form.registration_deadline"
                            :max="form.starts_on"
                            clearable
                            class="mt-1"
                        />
                        <p v-if="form.errors.registration_deadline" class="mt-1 text-xs text-red-600">
                            {{ form.errors.registration_deadline }}
                        </p>
                    </div>

                    <div>
                        <InputLabel for="closure-note" :value="$t('closures.note')" />
                        <TextInput
                            id="closure-note"
                            v-model="form.note"
                            type="text"
                            maxlength="255"
                            class="mt-1 block w-full"
                            :placeholder="$t('closures.note_placeholder')"
                        />
                    </div>
                </div>

                <p v-if="isCare" class="mt-2 text-xs text-ink/50">
                    {{ $t('care.generates_days', { start: careDefaults.starts_at, end: careDefaults.ends_at }) }}
                </p>

                <div class="mt-3 flex justify-end gap-3">
                    <SecondaryButton v-if="editingId" @click="cancel">
                        {{ $t('common.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton
                        data-testid="closure-save"
                        :disabled="form.processing || !form.name || !form.starts_on || !form.ends_on"
                        @click="submit"
                    >
                        {{ editingId ? $t('common.save') : $t('closures.add') }}
                    </PrimaryButton>
                </div>
            </div>

            <!-- Ferienbetreuung: each period with the days it offers -->
            <div v-if="care.length" class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="mb-3 font-semibold text-ink">{{ $t('care.heading') }}</p>

                <div
                    v-for="period in care"
                    :key="period.id"
                    :data-testid="`care-period-${period.id}`"
                    class="border-b border-ink/5 py-3 first:pt-0 last:border-0 last:pb-0"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-ink">{{ period.name }}</p>
                            <p class="text-sm text-ink/60">
                                {{ rangeLabel(period) }}
                                <span class="text-ink/40">
                                    ·
                                    {{
                                        period.day_count === 1
                                            ? $t('closures.day_one')
                                            : $t('closures.day_many', { count: period.day_count })
                                    }}
                                </span>
                            </p>
                            <p class="mt-0.5 text-xs" :class="period.registration_open ? 'text-ink/50' : 'text-hort-orange-dark'">
                                <template v-if="period.registration_deadline">
                                    {{
                                        period.registration_open
                                            ? $t('care.deadline_open', { date: dateLabel(period.registration_deadline) })
                                            : $t('care.deadline_passed', { date: dateLabel(period.registration_deadline) })
                                    }}
                                </template>
                                <template v-else>{{ $t('care.no_deadline') }}</template>
                            </p>
                            <p v-if="period.note" class="mt-0.5 text-xs text-ink/50">{{ period.note }}</p>
                            <!-- Essen + Aktivität live on /program; jump straight to that
                                 week rather than making staff click through to it. -->
                            <Link
                                v-if="canManage"
                                :href="programRoute({ query: { week: period.starts_on } }).url"
                                :data-testid="`care-program-${period.id}`"
                                class="mt-1 inline-block text-xs font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                            >
                                {{ $t('care.plan_program') }}
                            </Link>
                        </div>

                        <div v-if="canManage" class="flex shrink-0 items-center gap-2">
                            <template v-if="confirmingDelete === period.id">
                                <span class="text-sm text-ink/60">{{ $t('closures.delete_confirm') }}</span>
                                <DangerButton @click="remove(period)">{{ $t('common.delete') }}</DangerButton>
                                <SecondaryButton @click="confirmingDelete = null">
                                    {{ $t('common.cancel') }}
                                </SecondaryButton>
                            </template>
                            <template v-else>
                                <SecondaryButton
                                    :data-testid="`care-edit-${period.id}`"
                                    @click="edit(period, 'care')"
                                >
                                    {{ $t('common.edit') }}
                                </SecondaryButton>
                                <SecondaryButton @click="confirmingDelete = period.id">
                                    {{ $t('common.delete') }}
                                </SecondaryButton>
                            </template>
                        </div>
                    </div>

                    <ul class="mt-2 divide-y divide-ink/5">
                        <CareDayRow
                            v-for="day in period.days"
                            :key="day.id"
                            :day="day"
                            :can-manage="canManage"
                        />
                    </ul>

                    <!-- Days staff took out of the period: removing one is undoable,
                         but re-saving the period deliberately won't bring it back. -->
                    <div v-if="canManage && period.removed_days.length" class="mt-3 border-t border-ink/5 pt-2">
                        <p class="text-xs font-medium text-ink/50">{{ $t('care.removed_heading') }}</p>
                        <div
                            v-for="day in period.removed_days"
                            :key="day.id"
                            :data-testid="`care-day-removed-${day.id}`"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 py-1 text-sm"
                        >
                            <span class="w-24 shrink-0 text-ink/50">{{ dayLabel(day.date) }}</span>
                            <SecondaryButton
                                :data-testid="`care-day-restore-${day.id}`"
                                @click="restoreDay(day)"
                            >
                                {{ $t('care.restore_day') }}
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming -->
            <div class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="mb-3 font-semibold text-ink">{{ $t('closures.upcoming_heading') }}</p>

                <p v-if="!upcoming.length" class="text-sm text-ink/50" data-testid="closures-empty">
                    {{ $t('closures.none') }}
                </p>

                <ul v-else class="divide-y divide-ink/5">
                    <li
                        v-for="period in upcoming"
                        :key="period.id"
                        :data-testid="`closure-${period.id}`"
                        class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div>
                            <p class="font-medium text-ink">{{ period.name }}</p>
                            <p class="text-sm text-ink/60">
                                {{ rangeLabel(period) }}
                                <span class="text-ink/40">
                                    ·
                                    {{
                                        period.days === 1
                                            ? $t('closures.day_one')
                                            : $t('closures.day_many', { count: period.days })
                                    }}
                                </span>
                            </p>
                            <p v-if="period.note" class="mt-0.5 text-xs text-ink/50">{{ period.note }}</p>
                        </div>

                        <div v-if="canManage" class="flex shrink-0 items-center gap-2">
                            <template v-if="confirmingDelete === period.id">
                                <span class="text-sm text-ink/60">{{ $t('closures.delete_confirm') }}</span>
                                <DangerButton
                                    :data-testid="`closure-delete-confirm-${period.id}`"
                                    @click="remove(period)"
                                >
                                    {{ $t('common.delete') }}
                                </DangerButton>
                                <SecondaryButton @click="confirmingDelete = null">
                                    {{ $t('common.cancel') }}
                                </SecondaryButton>
                            </template>
                            <template v-else>
                                <SecondaryButton
                                    :data-testid="`closure-edit-${period.id}`"
                                    @click="edit(period)"
                                >
                                    {{ $t('common.edit') }}
                                </SecondaryButton>
                                <SecondaryButton
                                    :data-testid="`closure-delete-${period.id}`"
                                    @click="confirmingDelete = period.id"
                                >
                                    {{ $t('common.delete') }}
                                </SecondaryButton>
                            </template>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Past, collapsed -->
            <div v-if="past.length" class="rounded-2xl bg-surface p-4 shadow-sm">
                <button
                    type="button"
                    data-testid="closures-past-toggle"
                    class="text-sm font-medium text-ink/60 underline-offset-2 hover:underline"
                    @click="showPast = !showPast"
                >
                    {{ showPast ? $t('closures.hide_past') : $t('closures.show_past', { count: past.length }) }}
                </button>

                <ul v-if="showPast" class="mt-3 divide-y divide-ink/5">
                    <li v-for="period in past" :key="period.id" class="py-2 first:pt-0 last:pb-0">
                        <p class="text-sm text-ink/70">{{ period.name }}</p>
                        <p class="text-xs text-ink/50">{{ rangeLabel(period) }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
