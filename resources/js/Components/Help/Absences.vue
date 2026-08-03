<script setup>
// Hilfe-Kapitel „Wenn ein Kind nicht da ist" — the three different kinds of
// „not there", which is the part parents most often mix up.
import HelpSection from '@/Components/Help/HelpSection.vue';
</script>

<template>
    <div class="space-y-10">
        <p class="text-ink/70" v-html="$t('help.absences.intro')" />

        <HelpSection :title="$t('help.absences.sick_title')">
            <p class="text-ink/70" v-html="$t('help.absences.sick_text')" />
            <p class="text-ink/70" v-html="$t('help.absences.sick_undo')" />
            <p class="text-sm text-ink/60" v-html="$t('help.absences.sick_slack')" />
        </HelpSection>

        <HelpSection :title="$t('help.absences.hortfrei_title')">
            <p class="text-ink/70" v-html="$t('help.absences.hortfrei_text')" />
            <p class="text-sm text-ink/60" v-html="$t('help.absences.hortfrei_note')" />
        </HelpSection>

        <HelpSection :title="$t('help.absences.closed_title')">
            <p class="text-ink/70" v-html="$t('help.absences.closed_text')" />
            <p class="text-sm text-ink/60" v-html="$t('help.absences.closed_link')" />
        </HelpSection>
    </div>
</template>
