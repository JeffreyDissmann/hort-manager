<script setup>
import { email as passwordEmail } from '@/routes/password';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(passwordEmail().url);
};
</script>

<template>
    <GuestLayout>
        <Head title="Passwort vergessen" />

        <div class="mb-4 text-sm text-gray-600">
            Passwort vergessen? Kein Problem. Gib einfach deine E-Mail-Adresse
            an und wir senden dir einen Link, mit dem du ein neues Passwort
            wählen kannst.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="E-Mail-Adresse" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Link zum Zurücksetzen senden
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
