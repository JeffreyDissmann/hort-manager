<script setup>
import { update as childrenUpdate, index as childrenIndex, destroy as childrenDestroy } from '@/routes/children';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TimeSelect from '@/Components/TimeSelect.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const currentUserId = usePage().props.auth?.user?.id;

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
    canDelete: {
        type: Boolean,
        default: false,
    },
    canManageGuardians: {
        type: Boolean,
        default: false,
    },
    allParents: {
        type: Array,
        default: () => [],
    },
    guardianIds: {
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
        comment: day.comment ?? '',
    })),
    guardians: [...props.guardianIds],
});

function submit() {
    form.transform((data) => ({
        ...data,
        schedule: data.schedule.map((day) => ({
            ...day,
            method: day.method || null,
        })),
    })).patch(childrenUpdate(props.child.id).url);
}

function destroy() {
    if (
        confirm(
            `„${props.child.name}“ wirklich löschen? Der Stammplan geht verloren.`,
        )
    ) {
        router.delete(childrenDestroy(props.child.id).url);
    }
}
</script>

<template>
    <Head :title="`${child.name} – Stammplan`" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-hort-navy">
                {{ child.name }} – Stammplan
            </h2>
        </template>

        <div class="mx-auto max-w-3xl lg:max-w-none">
            <form
                @submit.prevent="submit"
                class="space-y-8 rounded-2xl bg-white p-6 shadow-sm"
            >
                    <!-- Stammdaten -->
                    <section class="space-y-6">
                        <h3 class="text-lg font-medium text-hort-navy">
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
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                            ></textarea>
                            <InputError :message="form.errors.note" class="mt-2" />
                        </div>
                    </section>

                    <!-- Stammplan -->
                    <section class="space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-hort-navy">
                                Stammplan (Mo–Fr)
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Trage für jeden Tag ein, wann das Kind regulär
                                abgeholt wird oder allein geht. Tage ohne Uhrzeit
                                gelten als hortfrei.
                            </p>
                        </div>

                        <!--
                            One card per weekday: stacked on mobile, a horizontal
                            Mo–Fr row (table-like) once there's room (lg).
                        -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <div
                                v-for="day in form.schedule"
                                :key="day.weekday"
                                class="space-y-2 rounded-lg border border-gray-200 p-3"
                            >
                                <div class="font-medium text-hort-navy lg:text-center">
                                    {{ weekdayNames[day.weekday] }}
                                </div>

                                <div>
                                    <InputLabel
                                        :for="`time-${day.weekday}`"
                                        value="Uhrzeit"
                                        class="sr-only"
                                    />
                                    <TimeSelect
                                        :id="`time-${day.weekday}`"
                                        v-model="day.planned_time"
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
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-hort-teal focus:ring-hort-teal disabled:bg-gray-100 disabled:text-gray-400"
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

                                <input
                                    v-if="day.planned_time"
                                    v-model="day.comment"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Kommentar, z. B. wegen Fußball"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                                />
                            </div>
                        </div>
                    </section>

                    <!-- Eltern-Zuordnung -->
                    <section v-if="canManageGuardians" class="space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-hort-navy">
                                Eltern
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Wähle die Eltern dieses Kindes – z. B. den anderen
                                Elternteil, damit ihr beide den Stammplan und
                                kurzfristige Änderungen pflegen könnt.
                            </p>
                        </div>

                        <div
                            v-if="allParents.length"
                            class="space-y-2 rounded-md border border-gray-200 p-2"
                        >
                            <label
                                v-for="parent in allParents"
                                :key="parent.id"
                                class="flex items-center gap-3 rounded-lg p-2"
                                :class="
                                    parent.id === currentUserId
                                        ? 'opacity-70'
                                        : 'cursor-pointer hover:bg-hort-sand'
                                "
                            >
                                <input
                                    type="checkbox"
                                    :value="parent.id"
                                    v-model="form.guardians"
                                    :disabled="parent.id === currentUserId"
                                    class="rounded border-gray-300 text-hort-teal-dark focus:ring-hort-teal disabled:opacity-60"
                                />
                                <span class="text-sm">
                                    <span class="font-medium text-hort-navy">
                                        {{ parent.name }}
                                    </span>
                                    <span
                                        v-if="parent.id === currentUserId"
                                        class="text-hort-teal-dark"
                                    >
                                        · du
                                    </span>
                                    <span class="text-gray-500">
                                        · {{ parent.email }}
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p v-else class="text-sm text-gray-500">
                            Noch keine Eltern-Konten vorhanden.
                        </p>
                    </section>

                    <div class="flex items-center justify-between gap-4">
                        <button
                            v-if="canDelete"
                            type="button"
                            @click="destroy"
                            class="text-sm font-medium text-red-600 transition hover:text-red-700"
                        >
                            Kind löschen
                        </button>
                        <span v-else></span>

                        <div class="flex items-center gap-4">
                            <Link
                                :href="childrenIndex().url"
                                class="text-sm text-gray-600 hover:text-gray-900"
                            >
                                Abbrechen
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                Speichern
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
    </AuthenticatedLayout>
</template>
