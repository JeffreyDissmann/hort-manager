<script setup>
// Schließzeiten — Hort-wide days on which the Hort is closed. Staff manage them here;
// parents read the same page to plan around them.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { store as closuresStore, update as closuresUpdate, destroy as closuresDestroy } from '@/routes/closures';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    upcoming: { type: Array, default: () => [] },
    past: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const flash = computed(() => usePage().props.flash?.status);
const locale = computed(() => usePage().props.locale || 'de');

const form = useForm({ name: '', starts_on: '', ends_on: '', note: '' });
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

function edit(period) {
    editingId.value = period.id;
    form.clearErrors();
    form.name = period.name;
    form.starts_on = period.starts_on;
    form.ends_on = period.ends_on;
    form.note = period.note ?? '';
}

function cancel() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: cancel };

    if (editingId.value) {
        form.patch(closuresUpdate(editingId.value).url, options);
    } else {
        form.post(closuresStore().url, options);
    }
}

function remove(period) {
    router.delete(closuresDestroy(period.id).url, {
        preserveScroll: true,
        onFinish: () => (confirmingDelete.value = null),
    });
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
