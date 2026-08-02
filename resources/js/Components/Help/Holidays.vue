<script setup>
// Hilfe-Kapitel „Ferien & Schließzeiten" — closures and the Ferienbetreuung
// sign-up, the one flow where parents have to do something themselves.
import HelpBullets from '@/Components/Help/HelpBullets.vue';
import HelpSection from '@/Components/Help/HelpSection.vue';
</script>

<template>
    <div class="space-y-10">
        <p class="text-ink/70" v-html="$t('help.holidays.intro')" />

        <HelpSection :title="$t('help.holidays.closed_title')">
            <p class="text-ink/70" v-html="$t('help.holidays.closed_text')" />
        </HelpSection>

        <HelpSection :title="$t('help.holidays.care_title')">
            <p class="text-ink/70" v-html="$t('help.holidays.care_text')" />
            <HelpBullets items-key="help.holidays.care_points" numbered />
        </HelpSection>

        <HelpSection :title="$t('help.holidays.care_day_title')">
            <p class="text-ink/70" v-html="$t('help.holidays.care_day_text')" />
            <HelpBullets items-key="help.holidays.care_day_points" />
        </HelpSection>
    </div>
</template>
