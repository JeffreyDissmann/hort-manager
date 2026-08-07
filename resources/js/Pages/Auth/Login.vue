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
import { computed, nextTick, ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const slackError = computed(() => usePage().props.errors?.slack);

// Folded away: someone who already has an account should meet the two fields, not
// a wall of onboarding text.
const showFirstSteps = ref(false);
const firstSteps = ref(null);

/**
 * The panel opens below the fold on a phone, so unfolding it looks like nothing
 * happened. Scroll it into view — but only on opening, and not against a reader who
 * asked for less motion.
 */
async function toggleFirstSteps() {
    showFirstSteps.value = !showFirstSteps.value;

    if (! showFirstSteps.value) {
        return;
    }

    await nextTick();

    firstSteps.value?.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        block: 'nearest',
    });
}

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

        <div class="my-6 flex items-center gap-3 text-xs text-ink/40">
            <span class="h-px flex-1 bg-ink/15" />
            {{ $t('login.or_with_email') }}
            <span class="h-px flex-1 bg-ink/15" />
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
                    <span class="ms-2 text-sm text-ink/70"
                        >{{ $t('login.remember_me') }}</span
                    >
                </label>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="passwordRequest().url"
                    class="rounded-md text-sm text-ink/70 underline hover:text-ink focus:outline-none focus:ring-2 focus:ring-hort-teal focus:ring-offset-2"
                >
                    {{ $t('login.forgot_password') }}
                </Link>

                <PrimaryButton
                    data-testid="login"
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('login.sign_in') }}
                </PrimaryButton>
            </div>
        </form>

        <!-- First-time parents arrive after the holidays and have no account yet.
             Both ways in are explained here rather than on the help page, because
             this is the screen they are stuck on. -->
        <div class="mt-6 border-t border-ink/10 pt-4">
            <button
                type="button"
                data-testid="new-here"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-hort-teal/20 px-4 py-3 text-base font-semibold text-ink transition hover:bg-hort-teal/30 focus:outline-none focus:ring-2 focus:ring-hort-teal focus:ring-offset-2"
                :aria-expanded="showFirstSteps"
                @click="toggleFirstSteps"
            >
                <span aria-hidden="true">👋</span>
                {{ $t('login.first_time_here') }}
                <span class="text-ink/50" aria-hidden="true">{{ showFirstSteps ? '▾' : '▸' }}</span>
            </button>

            <div
                v-if="showFirstSteps"
                ref="firstSteps"
                data-testid="new-here-panel"
                class="mt-3 space-y-3 text-sm text-ink/70"
            >
                <div>
                    <p class="font-semibold text-ink">{{ $t('login.first_slack_title') }}</p>
                    <p class="mt-0.5" v-html="$t('login.first_slack_text')" />
                </div>
                <!-- The trap: someone who isn't in the Slack keeps retrying that button
                     instead of taking the other way in. Say so between the two steps. -->
                <p
                    class="rounded-lg bg-hort-orange/10 px-3 py-2 text-hort-orange-dark"
                    v-html="$t('login.first_slack_fallback')"
                />
                <div>
                    <p class="font-semibold text-ink">{{ $t('login.first_password_title') }}</p>
                    <!-- The link's styling lives here, not in the lang file. -->
                    <p
                        class="mt-0.5 [&_a]:text-hort-teal-dark [&_a]:underline [&_a]:underline-offset-2 [&_a:hover]:text-ink"
                        v-html="$t('login.first_password_text', { url: passwordRequest().url })"
                    />
                </div>
                <p class="text-ink/50">{{ $t('login.first_stuck') }}</p>
                <p>
                    <Link :href="help().url" class="font-medium text-hort-teal-dark underline hover:text-ink">
                        {{ $t('login.how_it_works') }}
                    </Link>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
