<script setup>
// Plain-language manual, shown on the Hilfe page (guest and logged-in).
// Written for both parents (Eltern) and staff (Erzieher:innen).
import { t } from '@/i18n';
import { computed } from 'vue';

const firstSteps = computed(() => [0, 1, 2, 3].map((i) => t(`help.steps.${i}`)));

const areas = computed(() => [
    { icon: '☀️', key: 'today' },
    { icon: '📅', key: 'pickup_plan' },
    { icon: '🚌', key: 'excursions' },
    { icon: '👧', key: 'children' },
    { icon: '🍽️', key: 'program' },
].map(({ icon, key }) => ({
    icon,
    title: t(`help.areas.${key}.title`),
    audience: t(`help.areas.${key}.audience`),
    text: t(`help.areas.${key}.text`),
})));

const slackPoints = computed(() => [0, 1, 2, 3].map((i) => t(`help.slack_points.${i}`)));

// Free-text examples the Slack assistant understands.
const assistantExamples = computed(() => [0, 1, 2, 3, 4].map((i) => t(`help.assistant_examples.${i}`)));

const glossary = computed(() => ['stammplan', 'pickup_plan', 'departure', 'absence'].map((key) => [
    t(`help.glossary.${key}.term`),
    t(`help.glossary.${key}.def`),
]));
</script>

<template>
    <div class="space-y-12">
        <!-- Intro -->
        <section class="space-y-3">
            <h2 class="text-2xl font-bold text-hort-navy">{{ $t('help.intro_title') }}</h2>
            <p class="text-hort-navy/70" v-html="$t('help.intro_text')" />
        </section>

        <!-- Quick start -->
        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.quick_start_title') }}</h3>
            <ol class="space-y-3 rounded-2xl bg-hort-teal/10 p-5">
                <li
                    v-for="(step, i) in firstSteps"
                    :key="i"
                    class="flex gap-3 text-sm text-hort-navy/80"
                >
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-hort-teal text-xs font-bold text-hort-navy"
                    >
                        {{ i + 1 }}
                    </span>
                    <span>{{ step }}</span>
                </li>
            </ol>
        </section>

        <!-- Login -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.login_title') }}</h3>
            <p class="text-hort-navy/70" v-html="$t('help.login_text_1')" />
            <p class="text-hort-navy/70" v-html="$t('help.login_text_2')" />
        </section>

        <!-- Areas -->
        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.areas_title') }}</h3>
            <ul class="grid gap-3 sm:grid-cols-2">
                <li
                    v-for="area in areas"
                    :key="area.title"
                    class="rounded-2xl bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-hort-navy">
                            <span class="mr-1">{{ area.icon }}</span> {{ area.title }}
                        </p>
                        <span
                            class="shrink-0 rounded-full bg-hort-navy/5 px-2 py-0.5 text-[11px] font-medium text-hort-navy/60"
                        >
                            {{ area.audience }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-hort-navy/70">{{ area.text }}</p>
                </li>
            </ul>
        </section>

        <!-- Slack -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.slack_title') }}</h3>
            <p class="text-hort-navy/70">
                {{ $t('help.slack_intro') }}
            </p>
            <ul class="space-y-2">
                <li
                    v-for="(point, i) in slackPoints"
                    :key="i"
                    class="flex gap-2 text-sm text-hort-navy/70"
                >
                    <span class="text-hort-teal-dark">✓</span>
                    <span>{{ point }}</span>
                </li>
            </ul>
        </section>

        <!-- Assistant & Krankmeldung -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.assistant_title') }}</h3>
            <p class="text-hort-navy/70" v-html="$t('help.assistant_text_1')" />
            <p class="text-hort-navy/70" v-html="$t('help.assistant_text_2')" />
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="(ex, i) in assistantExamples"
                    :key="i"
                    class="rounded-full bg-hort-teal/10 px-3 py-1 text-sm text-hort-navy/80"
                >
                    „{{ ex }}“
                </li>
            </ul>
            <p class="text-sm text-hort-navy/60">
                {{ $t('help.assistant_note') }}
            </p>
        </section>

        <!-- Install as app / notifications -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.install_title') }}</h3>
            <p class="text-hort-navy/70">
                {{ $t('help.install_text') }}
            </p>
            <ul class="space-y-2 text-sm text-hort-navy/70">
                <li v-html="$t('help.install_ios')" />
                <li v-html="$t('help.install_android')" />
                <li v-html="$t('help.install_enable')" />
            </ul>
            <p class="text-sm text-hort-navy/60">
                {{ $t('help.install_note') }}
            </p>
        </section>

        <!-- Roles -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.roles_title') }}</h3>
            <ul class="space-y-2 text-sm text-hort-navy/70">
                <li v-html="$t('help.role_parents')" />
                <li v-html="$t('help.role_staff')" />
                <li v-html="$t('help.role_admins')" />
            </ul>
        </section>

        <!-- Glossary -->
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.glossary_title') }}</h3>
            <dl class="space-y-3">
                <div v-for="[term, def] in glossary" :key="term" class="text-sm">
                    <dt class="font-semibold text-hort-navy">{{ term }}</dt>
                    <dd class="text-hort-navy/70">{{ def }}</dd>
                </div>
            </dl>
        </section>

        <!-- Questions -->
        <section class="space-y-2">
            <h3 class="text-lg font-semibold text-hort-navy">{{ $t('help.questions_title') }}</h3>
            <p class="text-hort-navy/70">
                <span v-html="$t('help.questions_text')" />
                <a
                    href="mailto:jeffrey@dissmann.net"
                    class="font-medium text-hort-teal-dark underline hover:text-hort-navy"
                >jeffrey@dissmann.net</a>.
            </p>
        </section>
    </div>
</template>
