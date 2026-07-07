<script setup>
import { ComputerDesktopIcon, MoonIcon, SunIcon } from '@heroicons/vue/24/outline';
import { preference, setTheme } from '@/theme';
import { t } from '@/i18n';

// Per-device theme preference (localStorage) — no server round-trip.
const options = [
    { value: 'system', label: () => t('profile.theme_system'), icon: ComputerDesktopIcon },
    { value: 'light', label: () => t('profile.theme_light'), icon: SunIcon },
    { value: 'dark', label: () => t('profile.theme_dark'), icon: MoonIcon },
];
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-ink">{{ $t('profile.theme') }}</h2>
            <p class="mt-1 text-sm text-ink/70">{{ $t('profile.theme_help') }}</p>
        </header>

        <div class="mt-6 grid max-w-md grid-cols-3 gap-2">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                class="flex flex-col items-center gap-2 rounded-lg border px-3 py-4 text-sm font-medium transition"
                :class="
                    preference === option.value
                        ? 'border-hort-teal bg-hort-teal/10 text-ink ring-1 ring-hort-teal'
                        : 'border-ink/15 text-ink/70 hover:bg-ink/5'
                "
                @click="setTheme(option.value)"
            >
                <component :is="option.icon" class="h-6 w-6" />
                {{ option.label() }}
            </button>
        </div>
    </section>
</template>
