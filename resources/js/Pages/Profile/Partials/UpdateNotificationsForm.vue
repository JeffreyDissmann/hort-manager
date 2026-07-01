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
            <h2 class="text-lg font-medium text-hort-navy">Benachrichtigungen</h2>

            <p class="mt-1 text-sm text-gray-600">
                Erhalte eine Push-Nachricht auf dieses Gerät, wenn dein Kind abgeholt
                wurde oder allein gegangen ist – und bei neuen Ausflügen samt
                Erinnerung zur Abstimmung.
            </p>
        </header>

        <div class="mt-6 max-w-xl">
            <div v-if="supported" class="flex items-center justify-between gap-4">
                <span class="text-sm font-medium text-hort-navy">
                    Push-Benachrichtigungen
                    <span
                        class="ml-1 text-xs font-normal"
                        :class="subscribed ? 'text-hort-teal-dark' : 'text-gray-400'"
                    >
                        {{ subscribed ? 'An' : 'Aus' }}
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

            <p v-else class="text-sm text-gray-500">
                Dieser Browser kann keine Benachrichtigungen anzeigen. Auf dem iPhone
                musst du die App dafür zuerst über <strong>Teilen → Zum
                Home-Bildschirm</strong> installieren und sie von dort öffnen.
            </p>

            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

            <p class="mt-4 text-xs text-gray-500">
                Die Einstellung gilt pro Gerät – aktiviere sie auf jedem Handy oder
                Rechner, auf dem du benachrichtigt werden möchtest.
            </p>
        </div>
    </section>
</template>
