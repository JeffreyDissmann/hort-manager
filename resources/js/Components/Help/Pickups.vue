<script setup>
// Hilfe-Kapitel „Abholung & Wochenplan" — the heart of the app.
import HelpBullets from '@/Components/Help/HelpBullets.vue';
import HelpSection from '@/Components/Help/HelpSection.vue';
</script>

<template>
    <div class="space-y-10">
        <p class="text-ink/70" v-html="$t('help.pickups.intro')" />

        <HelpSection :title="$t('help.pickups.stammplan_title')">
            <p class="text-ink/70" v-html="$t('help.pickups.stammplan_text')" />
            <p class="text-sm text-ink/60" v-html="$t('help.pickups.stammplan_where')" />
        </HelpSection>

        <HelpSection :title="$t('help.pickups.week_title')">
            <p class="text-ink/70" v-html="$t('help.pickups.week_text')" />
            <p class="text-ink/70" v-html="$t('help.pickups.week_edit')" />
        </HelpSection>

        <HelpSection :title="$t('help.pickups.today_title')">
            <p class="text-ink/70" v-html="$t('help.pickups.today_text')" />
        </HelpSection>

        <HelpSection :title="$t('help.pickups.companion_title')">
            <p class="text-ink/70">{{ $t('help.pickups.companion_intro') }}</p>
            <HelpBullets items-key="help.pickups.companion_points" />
        </HelpSection>

        <HelpSection :title="$t('help.pickups.late_title')">
            <p class="text-ink/70" v-html="$t('help.pickups.late_text')" />
        </HelpSection>
    </div>
</template>
