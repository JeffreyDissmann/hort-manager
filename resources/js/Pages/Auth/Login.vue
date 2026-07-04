<script setup>
import { login, help } from '@/routes';
import { redirect as slackRedirect } from '@/routes/slack';
import { request as passwordRequest } from '@/routes/password';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const slackError = computed(() => usePage().props.errors?.slack);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(login().url, {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('login.title')" />

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div
            v-if="slackError"
            class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-600"
        >
            {{ slackError }}
        </div>

        <!-- Primary login for parents: Sign in with Slack -->
        <a
            :href="slackRedirect().url"
            class="flex w-full items-center justify-center gap-2.5 rounded-lg bg-[#4A154B] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#611f64] focus:outline-none focus:ring-2 focus:ring-hort-teal focus:ring-offset-2"
        >
            <svg class="h-5 w-5" viewBox="0 0 122.8 122.8" xmlns="http://www.w3.org/2000/svg">
                <path d="M25.8 77.6c0 7.1-5.8 12.9-12.9 12.9S0 84.7 0 77.6s5.8-12.9 12.9-12.9h12.9v12.9z" fill="#e01e5a" />
                <path d="M32.3 77.6c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9v32.3c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V77.6z" fill="#e01e5a" />
                <path d="M45.2 25.8c-7.1 0-12.9-5.8-12.9-12.9S38.1 0 45.2 0s12.9 5.8 12.9 12.9v12.9H45.2z" fill="#36c5f0" />
                <path d="M45.2 32.3c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H12.9C5.8 58.1 0 52.3 0 45.2s5.8-12.9 12.9-12.9h32.3z" fill="#36c5f0" />
                <path d="M97 45.2c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9-5.8 12.9-12.9 12.9H97V45.2z" fill="#2eb67d" />
                <path d="M90.5 45.2c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V12.9C64.7 5.8 70.5 0 77.6 0s12.9 5.8 12.9 12.9v32.3z" fill="#2eb67d" />
                <path d="M77.6 97c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9-12.9-5.8-12.9-12.9V97h12.9z" fill="#ecb22e" />
                <path d="M77.6 90.5c-7.1 0-12.9-5.8-12.9-12.9s5.8-12.9 12.9-12.9h32.3c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H77.6z" fill="#ecb22e" />
            </svg>
            {{ $t('login.sign_in_with_slack') }}
        </a>

        <div class="my-6 flex items-center gap-3 text-xs text-gray-400">
            <span class="h-px flex-1 bg-gray-200" />
            {{ $t('login.or_with_email') }}
            <span class="h-px flex-1 bg-gray-200" />
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" :value="$t('login.email')" />

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

            <div class="mt-4">
                <InputLabel for="password" :value="$t('login.password')" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600"
                        >{{ $t('login.remember_me') }}</span
                    >
                </label>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="passwordRequest().url"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-hort-teal focus:ring-offset-2"
                >
                    {{ $t('login.forgot_password') }}
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('login.sign_in') }}
                </PrimaryButton>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            {{ $t('login.new_here') }}
            <Link :href="help().url" class="font-medium text-hort-teal-dark underline hover:text-hort-navy">
                {{ $t('login.how_it_works') }}
            </Link>
        </p>
    </GuestLayout>
</template>
