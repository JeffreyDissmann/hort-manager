<script setup>
import { usePush } from '@/composables/usePush';
import { onMounted } from 'vue';

const { supported, subscribed, busy, error, refresh, enable, disable } = usePush();

onMounted(refresh);

function toggle() {
    if (busy.value) {
        return;
    }
    subscribed.value ? disable() : enable();
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-hort-navy">{{ $t('profile.notifications_title') }}</h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ $t('profile.notifications_description') }}
            </p>
        </header>

        <div class="mt-6 max-w-xl">
            <div v-if="supported" class="flex items-center justify-between gap-4">
                <span class="text-sm font-medium text-hort-navy">
                    {{ $t('profile.push') }}
                    <span
                        class="ml-1 text-xs font-normal"
                        :class="subscribed ? 'text-hort-teal-dark' : 'text-gray-400'"
                    >
                        {{ subscribed ? $t('profile.on') : $t('profile.off') }}
                    </span>
                </span>

                <button
                    type="button"
                    role="switch"
                    :aria-checked="subscribed"
                    :disabled="busy"
                    @click="toggle"
                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-50"
                    :class="subscribed ? 'bg-hort-teal-dark' : 'bg-gray-300'"
                >
                    <span
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                        :class="subscribed ? 'translate-x-6' : 'translate-x-1'"
                    />
                </button>
            </div>

            <p v-else class="text-sm text-gray-500" v-html="$t('profile.notifications_unsupported')" />

            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

            <p class="mt-4 text-xs text-gray-500">
                {{ $t('profile.notifications_per_device') }}
            </p>
        </div>
    </section>
</template>
