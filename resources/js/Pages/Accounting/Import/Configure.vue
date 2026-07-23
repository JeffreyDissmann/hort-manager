<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { storeMapping as importStoreMapping } from '@/routes/accounting/import';
import { CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    batch: { type: Object, required: true }, // { id, filename, account }
    header: { type: Array, required: true }, // column names
    preview: { type: Array, required: true }, // sample rows (array of string cells)
    rowCount: { type: Number, required: true },
    mapping: { type: Object, required: true }, // field -> column index | null (best guess)
    fields: { type: Array, required: true },
    requiredFields: { type: Array, required: true },
});

const form = useForm({ mapping: { ...props.mapping } });

const isRequired = (field) => props.requiredFields.includes(field);

// Which field (if any) each column is currently assigned to — drives the preview
// highlighting and warns when one column is mapped to two fields.
const fieldOfColumn = computed(() => {
    const map = {};
    for (const field of props.fields) {
        const index = form.mapping[field];
        if (index !== null && index !== undefined) {
            map[index] = field;
        }
    }
    return map;
});

function submit() {
    form.post(importStoreMapping(props.batch.id).url);
}
</script>

<template>
    <Head :title="$t('accounting.import.configure_title')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.import.configure_title') }}</h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-5">
            <div class="rounded-2xl bg-surface p-6 shadow-sm">
                <div class="mb-1 flex items-center gap-3">
                    <p class="font-medium text-ink">{{ batch.filename }}</p>
                    <span class="text-sm text-ink/50">· {{ batch.account }}</span>
                </div>
                <p class="text-sm text-ink/60">{{ $t('accounting.import.configure_intro', { count: rowCount }) }}</p>

                <!-- Field → column mapping -->
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div v-for="field in fields" :key="field">
                        <label :for="'map-' + field" class="flex items-center gap-1.5 text-sm font-medium text-ink">
                            {{ $t(`accounting.import.field_${field}`) }}
                            <span v-if="isRequired(field)" class="text-red-500">*</span>
                        </label>
                        <select
                            :id="'map-' + field"
                            v-model="form.mapping[field]"
                            class="mt-1 block w-full rounded-md border-ink/20 text-sm shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                        >
                            <option v-if="!isRequired(field)" :value="null">{{ $t('accounting.import.column_none') }}</option>
                            <option v-for="(name, i) in header" :key="i" :value="i">
                                {{ name || $t('accounting.import.column_n', { n: i + 1 }) }}
                            </option>
                        </select>
                        <InputError :message="form.errors[`mapping.${field}`]" class="mt-1" />
                    </div>
                </div>
            </div>

            <!-- Preview: highlight each column that a field is mapped to -->
            <div class="overflow-hidden rounded-2xl bg-surface shadow-sm">
                <p class="border-b border-ink/5 px-3 py-2 text-xs text-ink/50">
                    {{ $t('accounting.import.preview_note', { shown: preview.length, total: rowCount }) }}
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-ink/10 text-xs text-ink/50">
                            <tr>
                                <th v-for="(name, i) in header" :key="i" class="px-3 py-2 align-bottom">
                                    <span
                                        v-if="fieldOfColumn[i]"
                                        class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-hort-teal-dark"
                                    >
                                        {{ $t(`accounting.import.field_${fieldOfColumn[i]}`) }}
                                    </span>
                                    <span class="font-medium text-ink/70">{{ name || $t('accounting.import.column_n', { n: i + 1 }) }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/5">
                            <tr v-for="(row, r) in preview" :key="r">
                                <td
                                    v-for="(name, i) in header"
                                    :key="i"
                                    class="max-w-[16rem] truncate px-3 py-1.5"
                                    :class="fieldOfColumn[i] ? 'text-ink' : 'text-ink/40'"
                                >
                                    {{ row[i] ?? '' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <PrimaryButton :disabled="form.processing" @click="submit">
                    <CheckCircleIcon class="mr-1 h-4 w-4" /> {{ $t('accounting.import.confirm_mapping') }}
                </PrimaryButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
