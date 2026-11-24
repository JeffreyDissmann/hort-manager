<script setup>
// Hilfe-Kapitel „Springerdienst" — what the list is, how families swap a day,
// and how the Springer-Koordination fills it.
import HelpBullets from '@/Components/Help/HelpBullets.vue';
import HelpSection from '@/Components/Help/HelpSection.vue';
</script>

<template>
    <div class="space-y-10">
        <p class="text-ink/70" v-html="$t('help.standby.intro')" />
        <HelpBullets items-key="help.standby.points" marker="🦺" />

        <HelpSection :title="$t('help.standby.swap_title')">
            <HelpBullets items-key="help.standby.swap_points" />
        </HelpSection>

        <HelpSection :title="$t('help.standby.coordinator_title')">
            <p class="text-ink/70" v-html="$t('help.standby.coordinator_text')" />
            <HelpBullets items-key="help.standby.coordinator_points" />
        </HelpSection>
    </div>
</template>
