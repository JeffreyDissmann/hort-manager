<script setup>
// Hilfe-Kapitel „Anmelden & einrichten".
import HelpSection from '@/Components/Help/HelpSection.vue';
</script>

<template>
    <div class="space-y-10">
        <HelpSection :title="$t('help.getting-started.login_title')">
            <p class="text-ink/70" v-html="$t('help.getting-started.login_text_1')" />
            <p class="text-ink/70" v-html="$t('help.getting-started.login_text_2')" />
        </HelpSection>

        <HelpSection :title="$t('help.getting-started.children_title')">
            <p class="text-ink/70" v-html="$t('help.getting-started.children_text')" />
        </HelpSection>

        <HelpSection :title="$t('help.getting-started.install_title')">
            <p class="text-ink/70">{{ $t('help.getting-started.install_text') }}</p>
            <ul class="space-y-2 text-sm text-ink/70">
                <li v-html="$t('help.getting-started.install_ios')" />
                <li v-html="$t('help.getting-started.install_android')" />
                <li v-html="$t('help.getting-started.install_enable')" />
            </ul>
            <p class="text-sm text-ink/60">{{ $t('help.getting-started.install_note') }}</p>
        </HelpSection>

        <HelpSection :title="$t('help.getting-started.appearance_title')">
            <ul class="space-y-2 text-sm text-ink/70">
                <li v-html="$t('help.getting-started.appearance_theme')" />
                <li v-html="$t('help.getting-started.appearance_language')" />
            </ul>
        </HelpSection>
    </div>
</template>
