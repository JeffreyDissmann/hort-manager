<script setup>
import { update as passwordUpdate } from '@/routes/password';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    // Slack-only users have no password yet, so the form just sets one.
    hasPassword: {
        type: Boolean,
        default: true,
    },
});

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(passwordUpdate().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-ink">
                {{ hasPassword ? $t('profile.password_change') : $t('profile.password_set') }}
            </h2>

            <p class="mt-1 text-sm text-ink/70">
                <template v-if="hasPassword">
                    {{ $t('profile.password_help') }}
                </template>
                <template v-else>
                    {{ $t('profile.password_help_slack') }}
                </template>
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
            <div v-if="hasPassword">
                <InputLabel for="current_password" :value="$t('profile.current_password')" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-2"
                />
            </div>

            <div>
                <InputLabel for="password" :value="$t('profile.new_password')" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    :value="$t('profile.confirm_password')"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-2"
                />
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
