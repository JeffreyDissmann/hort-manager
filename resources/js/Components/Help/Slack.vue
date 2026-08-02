<script setup>
// Hilfe-Kapitel „Slack & Benachrichtigungen": what the app sends, and the
// free-text assistant, which is the fastest way to report anything.
import HelpBullets from '@/Components/Help/HelpBullets.vue';
import HelpSection from '@/Components/Help/HelpSection.vue';
import { tList } from '@/i18n';
import { computed } from 'vue';

const examples = computed(() => tList('help.slack.assistant_examples'));
</script>

<template>
    <div class="space-y-10">
        <HelpSection :title="$t('help.slack.slack_title')">
            <p class="text-ink/70">{{ $t('help.slack.slack_intro') }}</p>
            <HelpBullets items-key="help.slack.slack_points" />
        </HelpSection>

        <HelpSection :title="$t('help.slack.assistant_title')">
            <p class="text-ink/70" v-html="$t('help.slack.assistant_text')" />
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="(ex, i) in examples"
                    :key="i"
                    class="rounded-full bg-hort-teal/10 px-3 py-1 text-sm text-ink/80"
                >
                    „{{ ex }}“
                </li>
            </ul>
            <p class="text-sm text-ink/60">{{ $t('help.slack.assistant_note') }}</p>
        </HelpSection>

        <HelpSection :title="$t('help.slack.notifications_title')">
            <p class="text-ink/70" v-html="$t('help.slack.notifications_intro')" />
            <HelpBullets items-key="help.slack.notifications_points" marker="🔔" />
            <p class="text-sm text-ink/60" v-html="$t('help.slack.notifications_settings')" />
        </HelpSection>
    </div>
</template>
