<script setup>
// Hilfe-Kapitel „Kurz erklärt": the app's vocabulary, plus where to get help.
import HelpSection from '@/Components/Help/HelpSection.vue';
import { t } from '@/i18n';
import { computed } from 'vue';

const terms = computed(() =>
    ['stammplan', 'pickup_plan', 'departure', 'companion', 'absence', 'hortfrei', 'closure', 'care'].map((key) => ({
        term: t(`help.glossary.terms.${key}.term`),
        def: t(`help.glossary.terms.${key}.def`),
    })),
);
</script>

<template>
    <div class="space-y-10">
        <p class="text-ink/70">{{ $t('help.glossary.intro') }}</p>

        <dl class="space-y-3">
            <div v-for="entry in terms" :key="entry.term" class="text-sm">
                <dt class="font-semibold text-ink">{{ entry.term }}</dt>
                <dd class="text-ink/70">{{ entry.def }}</dd>
            </div>
        </dl>

        <HelpSection :title="$t('help.glossary.questions_title')">
            <p class="text-ink/70">
                <span v-html="$t('help.glossary.questions_text')" />
                <a
                    href="mailto:jeffrey@dissmann.net"
                    class="font-medium text-hort-teal-dark underline hover:text-ink"
                >jeffrey@dissmann.net</a>.
            </p>
        </HelpSection>
    </div>
</template>
