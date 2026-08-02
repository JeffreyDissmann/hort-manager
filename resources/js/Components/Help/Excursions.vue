<script setup>
// Hilfe-Kapitel „Ausflüge" — short on purpose: for parents it's two buttons.
import HelpBullets from '@/Components/Help/HelpBullets.vue';
</script>

<template>
    <div class="space-y-6">
        <p class="text-ink/70" v-html="$t('help.excursions.intro')" />
        <HelpBullets items-key="help.excursions.points" marker="🚌" />
    </div>
</template>
