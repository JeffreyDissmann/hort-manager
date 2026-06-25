<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    date_of_birth: '',
    note: '',
});

function submit() {
    form.post(route('children.store'));
}
</script>

<template>
    <Head title="Kind hinzufügen" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-hort-navy">
                Kind hinzufügen
            </h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-2xl bg-white p-6 shadow-sm"
            >
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            autofocus
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
    </AuthenticatedLayout>
</template>
