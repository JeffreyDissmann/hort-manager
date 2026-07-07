<script setup>
import { update as profileUpdate } from '@/routes/profile';
import { send as verificationSend } from '@/routes/verification';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-ink">
                {{ $t('profile.information_title') }}
            </h2>

            <p class="mt-1 text-sm text-ink/70">
                {{ $t('profile.information_description') }}
            </p>
        </header>

        <form
            @submit.prevent="form.patch(profileUpdate().url)"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" :value="$t('profile.name')" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" :value="$t('profile.email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-ink">
                    {{ $t('profile.email_unverified') }}
                    <Link
                        :href="verificationSend().url"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-ink/70 underline hover:text-ink focus:outline-none focus:ring-2 focus:ring-hort-teal focus:ring-offset-2"
                    >
                        {{ $t('profile.email_resend') }}
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{ $t('profile.email_verification_sent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">{{ $t('common.save') }}</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-ink/70"
                    >
                        {{ $t('common.saved') }}
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
