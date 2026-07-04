<script setup>
import { send as verificationSend } from '@/routes/verification';
import { logout } from '@/routes';
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
    form.post(verificationSend().url);
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head :title="$t('login.verify_title')" />

        <div class="mb-4 text-sm text-gray-600">
            {{ $t('login.verify_intro') }}
        </div>

        <div
            class="mb-4 text-sm font-medium text-green-600"
            v-if="verificationLinkSent"
        >
            {{ $t('login.verify_link_sent') }}
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('login.resend_verification') }}
                </PrimaryButton>

                <Link
                    :href="logout().url"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-hort-teal focus:ring-offset-2"
                    >{{ $t('login.log_out') }}</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
