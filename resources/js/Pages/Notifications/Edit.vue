<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NotificationToggle from '@/Components/NotificationToggle.vue';
import { usePush } from '@/composables/usePush';
import { update as notificationsUpdate } from '@/routes/notifications';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';

const props = defineProps({
    preferences: { type: Object, required: true },
    categories: { type: Array, required: true },
    slackConnected: { type: Boolean, default: false },
});

// Local, editable copy of the matrix — we PATCH the whole thing on every change.
const prefs = reactive(JSON.parse(JSON.stringify(props.preferences)));

const { supported, subscribed, busy, error, refresh, enable, disable } = usePush();

onMounted(refresh);

function togglePush() {
    if (busy.value) {
        return;
    }
    subscribed.value ? disable() : enable();
}

function save() {
    router.patch(
        notificationsUpdate().url,
        { preferences: prefs },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head :title="$t('notifications.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-ink">
                {{ $t('notifications.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Device push master switch -->
                <div class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <section class="max-w-xl">
                        <header>
                            <h2 class="text-lg font-medium text-ink">{{ $t('profile.push') }}</h2>
                            <p class="mt-1 text-sm text-ink/70">{{ $t('notifications.description') }}</p>
                        </header>

                        <div class="mt-6">
                            <div v-if="supported" class="flex items-center justify-between gap-4">
                                <span class="text-sm font-medium text-ink">
                                    {{ $t('profile.push') }}
                                    <span
                                        class="ml-1 text-xs font-normal"
                                        :class="subscribed ? 'text-hort-teal-dark' : 'text-ink/40'"
                                    >
                                        {{ subscribed ? $t('profile.on') : $t('profile.off') }}
                                    </span>
                                </span>

                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="subscribed"
                                    :disabled="busy"
                                    @click="togglePush"
                                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-50"
                                    :class="subscribed ? 'bg-hort-teal-dark' : 'bg-ink/25'"
                                >
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-surface transition"
                                        :class="subscribed ? 'translate-x-6' : 'translate-x-1'"
                                    />
                                </button>
                            </div>

                            <p
                                v-else
                                class="text-sm text-ink/60"
                                v-html="$t('profile.notifications_unsupported')"
                            />

                            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

                            <p class="mt-4 text-xs text-ink/60">
                                {{ $t('profile.notifications_per_device') }}
                            </p>
                        </div>
                    </section>
                </div>

                <!-- The category × channel matrix -->
                <div class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <header class="max-w-2xl">
                        <h2 class="text-lg font-medium text-ink">{{ $t('notifications.matrix_title') }}</h2>
                        <p class="mt-1 text-sm text-ink/70">{{ $t('notifications.matrix_description') }}</p>
                    </header>

                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full min-w-md text-left">
                            <thead>
                                <tr class="border-b border-ink/10 text-xs uppercase tracking-wide text-ink/50">
                                    <th class="py-2 pr-4 font-medium"></th>
                                    <th class="px-3 py-2 text-center font-medium">
                                        {{ $t('notifications.channel_slack') }}
                                    </th>
                                    <th class="px-3 py-2 text-center font-medium">
                                        {{ $t('notifications.channel_push') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="category in categories"
                                    :key="category"
                                    class="border-b border-ink/5 align-top last:border-0"
                                >
                                    <td class="py-4 pr-4">
                                        <p class="text-sm font-medium text-ink">
                                            {{ $t(`notifications.categories.${category}.label`) }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-ink/60">
                                            {{ $t(`notifications.categories.${category}.help`) }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <div class="flex justify-center">
                                            <NotificationToggle
                                                v-model="prefs[category].slack"
                                                :disabled="!slackConnected"
                                                :aria-label="$t('notifications.channel_slack')"
                                                @change="save"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <div class="flex justify-center">
                                            <NotificationToggle
                                                v-model="prefs[category].push"
                                                :aria-label="$t('notifications.channel_push')"
                                                @change="save"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="!slackConnected" class="mt-4 text-xs text-ink/60">
                        {{ $t('notifications.slack_disabled_hint') }}
                    </p>
                    <p class="mt-2 text-xs text-ink/60">
                        {{ $t('notifications.push_hint') }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
