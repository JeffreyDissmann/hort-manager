<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    child: {
        type: Object,
        required: true,
    },
    schedule: {
        type: Array,
        default: () => [],
    },
    methodOptions: {
        type: Array,
        default: () => [],
    },
});

const weekdayNames = {
    1: 'Montag',
    2: 'Dienstag',
    3: 'Mittwoch',
    4: 'Donnerstag',
    5: 'Freitag',
};

const form = useForm({
    name: props.child.name,
    date_of_birth: props.child.date_of_birth ?? '',
    note: props.child.note ?? '',
    schedule: props.schedule.map((day) => ({
        weekday: day.weekday,
        planned_time: day.planned_time ? day.planned_time.slice(0, 5) : '',
        method: day.method ?? '',
    })),
});

function submit() {
    form.transform((data) => ({
        ...data,
        schedule: data.schedule.map((day) => ({
            ...day,
            method: day.method || null,
        })),
    })).patch(route('children.update', props.child.id));
}
</script>

<template>
    <Head :title="`${child.name} – Stammplan`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ child.name }} – Stammplan
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-8 bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <!-- Stammdaten -->
                    <section class="space-y-6">
                        <h3 class="text-lg font-medium text-gray-900">
                            Stammdaten
                        </h3>

                        <div>
                            <InputLabel for="name" value="Name" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="date_of_birth" value="Geburtsdatum" />
                            <TextInput
                                id="date_of_birth"
                                v-model="form.date_of_birth"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError
                                :message="form.errors.date_of_birth"
                                class="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel for="note" value="Hinweise (optional)" />
                            <textarea
                                id="note"
                                v-model="form.note"
                                rows="3"
                                placeholder="z. B. Abholberechtigte, Aktivitäten oder Hinweise zur Abholung …"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <InputError :message="form.errors.note" class="mt-2" />
                        </div>
                    </section>

                    <!-- Stammplan -->
                    <section class="space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Stammplan (Mo–Fr)
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Trage für jeden Tag ein, wann das Kind regulär
                                abgeholt wird oder allein geht. Tage ohne Uhrzeit
                                gelten als hortfrei.
                            </p>
                        </div>

                        <div class="divide-y divide-gray-100 rounded-md border border-gray-200">
                            <div
                                v-for="day in form.schedule"
                                :key="day.weekday"
                                class="grid grid-cols-1 items-center gap-3 p-4 sm:grid-cols-[8rem,1fr,1fr]"
                            >
                                <span class="font-medium text-gray-700">
                                    {{ weekdayNames[day.weekday] }}
                                </span>

                                <div>
                                    <InputLabel
                                        :for="`time-${day.weekday}`"
                                        value="Uhrzeit"
                                        class="sr-only"
                                    />
                                    <TextInput
                                        :id="`time-${day.weekday}`"
                                        v-model="day.planned_time"
                                        type="time"
                                        class="block w-full"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        :for="`method-${day.weekday}`"
                                        value="Art"
                                        class="sr-only"
                                    />
                                    <select
                                        :id="`method-${day.weekday}`"
                                        v-model="day.method"
                                        :disabled="!day.planned_time"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400"
                                    >
                                        <option value="">— bitte wählen —</option>
                                        <option
                                            v-for="option in methodOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="route('children.index')"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            Abbrechen
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            Speichern
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
