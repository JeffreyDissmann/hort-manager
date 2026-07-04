<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TimeSelect from '@/Components/TimeSelect.vue';
import InputError from '@/Components/InputError.vue';
import { ref, watch } from 'vue';

// The parent's useForm instance is passed in and bound directly.
const props = defineProps({
    form: { type: Object, required: true },
    // When set (create), offer a "next Friday" shortcut for the date.
    suggestedDate: { type: String, default: '' },
});

function pad(n) {
    return String(n).padStart(2, '0');
}

function toIso(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const today = toIso(new Date());

function minusDays(dateStr, n) {
    const [y, m, d] = dateStr.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    date.setDate(date.getDate() - n);
    return toIso(date);
}

function shortDate(dateStr) {
    const [, m, d] = dateStr.split('-');
    return `${d}.${m}.`;
}

// Rückmeldung defaults to 3 days before the trip, until the user sets it by hand.
const deadlineTouched = ref(!!props.form.rsvp_deadline);

watch(
    () => props.form.date,
    (date) => {
        if (!deadlineTouched.value && date) {
            props.form.rsvp_deadline = minusDays(date, 3);
        }
    },
    { immediate: true },
);

function pickFriday() {
    props.form.date = props.suggestedDate;
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <InputLabel for="name" :value="$t('excursions.field_name')" />
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                :placeholder="$t('excursions.field_name_placeholder')"
                autofocus
            />
            <InputError :message="form.errors.name" class="mt-2" />
        </div>

        <div>
            <InputLabel for="date" :value="$t('excursions.field_date')" />
            <button
                v-if="suggestedDate"
                type="button"
                class="mt-1 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold transition"
                :class="form.date === suggestedDate
                    ? 'bg-hort-teal text-hort-navy'
                    : 'bg-hort-teal/15 text-hort-teal-dark hover:bg-hort-teal/25'"
                @click="pickFriday"
            >
                {{ $t('excursions.next_friday', { date: shortDate(suggestedDate) }) }}
            </button>
            <TextInput
                id="date"
                v-model="form.date"
                type="date"
                :min="today"
                class="mt-2 block w-full"
            />
            <InputError :message="form.errors.date" class="mt-2" />
        </div>

        <div class="flex gap-3">
            <div class="flex-1">
                <InputLabel for="depart_at" :value="$t('excursions.depart')" />
                <TimeSelect
                    id="depart_at"
                    v-model="form.depart_at"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.depart_at" class="mt-2" />
            </div>
            <div class="flex-1">
                <InputLabel for="return_at" :value="$t('excursions.return')" />
                <TimeSelect
                    id="return_at"
                    v-model="form.return_at"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.return_at" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel for="rsvp_deadline" :value="$t('excursions.field_deadline')" />
            <TextInput
                id="rsvp_deadline"
                v-model="form.rsvp_deadline"
                type="date"
                :min="today"
                :max="form.date || undefined"
                class="mt-1 block w-full"
                @input="deadlineTouched = true"
                @change="deadlineTouched = true"
            />
            <p class="mt-1 text-sm text-gray-500">
                {{ $t('excursions.field_deadline_hint') }}
            </p>
            <InputError :message="form.errors.rsvp_deadline" class="mt-2" />
        </div>

        <div>
            <InputLabel for="note" :value="$t('excursions.field_note')" />
            <textarea
                id="note"
                v-model="form.note"
                rows="2"
                :placeholder="$t('excursions.field_note_placeholder')"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
            ></textarea>
            <InputError :message="form.errors.note" class="mt-2" />
        </div>

        <p class="rounded-xl bg-hort-teal/10 p-3 text-sm text-hort-navy/70">
            {{ $t('excursions.after_save_before')
            }}<strong>{{ $t('excursions.after_save_strong') }}</strong>{{ $t('excursions.after_save_after') }}
        </p>
    </div>
</template>
