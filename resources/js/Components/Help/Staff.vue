<script setup>
// Hilfe-Kapitel „Für Erzieher:innen" — everything only staff can do, in the order of
// a working day: the board first, then what gets planned for it. Readable by parents
// too: the page is open, and knowing the mechanics helps them.
import HelpBullets from '@/Components/Help/HelpBullets.vue';
import HelpSection from '@/Components/Help/HelpSection.vue';
</script>

<template>
    <div class="space-y-10">
        <p class="text-ink/70">{{ $t('help.staff.intro') }}</p>

        <HelpSection :title="$t('help.staff.board_title')">
            <p class="text-ink/70" v-html="$t('help.staff.board_text')" />
            <HelpBullets items-key="help.staff.board_points" />
        </HelpSection>

        <HelpSection :title="$t('help.staff.children_title')">
            <p class="text-ink/70" v-html="$t('help.staff.children_text')" />
            <HelpBullets items-key="help.staff.children_points" />
        </HelpSection>

        <HelpSection :title="$t('help.staff.excursions_title')">
            <HelpBullets items-key="help.staff.excursions_points" marker="🚌" />
        </HelpSection>

        <HelpSection :title="$t('help.staff.program_title')">
            <p class="text-ink/70" v-html="$t('help.staff.program_text')" />
            <p class="text-ink/70" v-html="$t('help.staff.program_reminder')" />
        </HelpSection>

        <HelpSection :title="$t('help.staff.holidays_title')">
            <HelpBullets items-key="help.staff.holidays_points" marker="🏖️" />
        </HelpSection>

        <HelpSection :title="$t('help.staff.notifications_title')">
            <HelpBullets items-key="help.staff.notifications_points" marker="🔔" />
        </HelpSection>

        <HelpSection :title="$t('help.staff.admin_title')">
            <HelpBullets items-key="help.staff.admin_points" />
        </HelpSection>

        <HelpSection :title="$t('help.staff.roles_title')">
            <ul class="space-y-2 text-sm text-ink/70">
                <li v-html="$t('help.staff.role_parents')" />
                <li v-html="$t('help.staff.role_staff')" />
                <li v-html="$t('help.staff.role_admins')" />
            </ul>
            <p class="text-sm text-ink/60">{{ $t('help.staff.roles_note') }}</p>
        </HelpSection>
    </div>
</template>
