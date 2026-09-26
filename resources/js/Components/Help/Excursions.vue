<script setup>
// Hilfe-Kapitel „Ausflüge" — for parents it's two buttons, plus what the
// Antwortschluss actually means and what happens to a pickup inside the trip.
import HelpBullets from '@/Components/Help/HelpBullets.vue';
import HelpSection from '@/Components/Help/HelpSection.vue';
</script>

<template>
    <div class="space-y-10">
        <p class="text-ink/70" v-html="$t('help.excursions.intro')" />
        <HelpBullets items-key="help.excursions.points" marker="🚌" />

        <HelpSection :title="$t('help.excursions.deadline_title')">
            <HelpBullets items-key="help.excursions.deadline_points" />
        </HelpSection>

        <HelpSection :title="$t('help.excursions.pickup_title')">
            <p class="text-ink/70" v-html="$t('help.excursions.pickup_text')" />
            <HelpBullets items-key="help.excursions.pickup_points" />
        </HelpSection>
    </div>
</template>
