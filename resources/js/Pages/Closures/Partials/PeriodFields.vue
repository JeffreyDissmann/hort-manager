<script setup>
// The fields of a Ferien-Zeitraum — used when creating one on the list page and when
// editing one on its own page, so both say exactly the same thing.
import DatePicker from '@/Components/DatePicker.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    // The type can only be chosen while creating: converting an existing
    // Ferienbetreuung would throw away every sign-up with it.
    showTypeToggle: { type: Boolean, default: false },
});

const isCare = computed(() => props.form.type === 'care');

// „Von" is the anchor: picking it forwards an empty or earlier „bis" along, so a
// single closed day (the common Brückentag case) needs just one click.
function onStartPicked(value) {
    props.form.starts_on = value;
    if (!props.form.ends_on || props.form.ends_on < value) {
        props.form.ends_on = value;
    }
}
</script>

<template>
    <div>
        <div v-if="showTypeToggle" class="mb-3 inline-flex rounded-lg bg-canvas p-0.5 text-sm font-semibold">
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

        <!-- Proportional columns, not fixed rem: the same fields sit on the wide Ferien
             list and on the narrower period page, and a fixed 16+12+12 pushed the
             Anmeldeschluss out of the card there. -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.6fr),minmax(0,1fr),minmax(0,1fr),minmax(0,1fr)]">
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
                <DatePicker id="closure-to" v-model="form.ends_on" :min="form.starts_on" class="mt-1" />
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

            <div class="sm:col-span-2 lg:col-span-4">
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
    </div>
</template>
