<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="E-Mail-BestÃ¤tigung" />

        <div class="mb-4 text-sm text-gray-600">
            Danke für deine Registrierung! Bitte bestätige deine E-Mail-Adresse
            über den Link, den wir dir gerade geschickt haben. Falls du keine
            E-Mail erhalten hast, senden wir dir gerne eine neue.
        </div>

        <div
            class="mb-4 text-sm font-medium text-green-600"
            v-if="verificationLinkSent"
        >
            Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    BestÃ¤tigungs-E-Mail erneut senden
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-hort-teal focus:ring-offset-2"
                    >Abmelden</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
