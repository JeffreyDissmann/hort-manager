<script setup>
// The Hilfe content itself: the topic overview (no topic) or one chapter.
// Shown inside the app shell for signed-in users and standalone for guests,
// hence a component rather than page markup.
import Absences from '@/Components/Help/Absences.vue';
import Excursions from '@/Components/Help/Excursions.vue';
import GettingStarted from '@/Components/Help/GettingStarted.vue';
import Glossary from '@/Components/Help/Glossary.vue';
import Holidays from '@/Components/Help/Holidays.vue';
import Pickups from '@/Components/Help/Pickups.vue';
import Slack from '@/Components/Help/Slack.vue';
import Staff from '@/Components/Help/Staff.vue';
import { help } from '@/routes';
import { t, tList } from '@/i18n';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // Slug of the chapter to show, or null for the overview.
    topic: { type: String, default: null },
});

// Slug → chapter component + the emoji on its card. The order is the order of
// the overview: roughly what a new parent needs, in that sequence.
const chapters = {
    'getting-started': { icon: '🚪', component: GettingStarted },
    pickups: { icon: '🕒', component: Pickups },
    absences: { icon: '🤒', component: Absences },
    holidays: { icon: '🏖️', component: Holidays },
    excursions: { icon: '🚌', component: Excursions },
    slack: { icon: '💬', component: Slack },
    staff: { icon: '🧑‍🏫', component: Staff },
    glossary: { icon: '📖', component: Glossary },
};

const topics = computed(() =>
    Object.entries(chapters).map(([slug, { icon }]) => ({
        slug,
        icon,
        url: help({ topic: slug }).url,
        title: t(`help.topics.${slug}.title`),
        teaser: t(`help.topics.${slug}.teaser`),
        audience: t(`help.audiences.${t(`help.topics.${slug}.audience`)}`),
    })),
);

const chapter = computed(() => (props.topic ? chapters[props.topic]?.component : null));
const overviewUrl = computed(() => help().url);
const steps = computed(() => tList('help.steps'));
</script>

<template>
    <!-- One chapter -->
    <div v-if="chapter" class="space-y-8">
        <Link
            :href="overviewUrl"
            data-testid="help-back"
            class="inline-flex items-center gap-1 text-sm font-medium text-hort-teal-dark hover:text-ink"
        >
            ← {{ $t('help.back_to_overview') }}
        </Link>

        <h2 class="text-2xl font-bold text-ink">{{ $t(`help.topics.${topic}.title`) }}</h2>

        <component :is="chapter" />

        <!-- Every chapter ends with the way on to the others. -->
        <nav class="border-t border-ink/10 pt-6">
            <p class="mb-3 text-sm font-semibold text-ink/60">{{ $t('help.topics_title') }}</p>
            <ul class="flex flex-wrap gap-2">
                <li v-for="item in topics" :key="item.slug">
                    <Link
                        :href="item.url"
                        class="rounded-full px-3 py-1 text-sm transition"
                        :class="
                            item.slug === topic
                                ? 'bg-hort-teal font-medium text-hort-navy'
                                : 'bg-ink/5 text-ink/70 hover:bg-ink/10'
                        "
                    >
                        {{ item.icon }} {{ item.title }}
                    </Link>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Overview -->
    <div v-else class="space-y-10">
        <section class="space-y-3">
            <h2 class="text-2xl font-bold text-ink">{{ $t('help.intro_title') }}</h2>
            <p class="text-ink/70" v-html="$t('help.intro_text')" />
        </section>

        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-ink">{{ $t('help.quick_start_title') }}</h3>
            <ol class="space-y-3 rounded-2xl bg-hort-teal/10 p-5">
                <li v-for="(step, i) in steps" :key="i" class="flex gap-3 text-sm text-ink/80">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-hort-teal text-xs font-bold text-hort-navy"
                    >
                        {{ i + 1 }}
                    </span>
                    <span>{{ step }}</span>
                </li>
            </ol>
        </section>

        <section class="space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ $t('help.topics_title') }}</h3>
                <p class="text-sm text-ink/60">{{ $t('help.topics_hint') }}</p>
            </div>

            <ul class="grid gap-3 sm:grid-cols-2">
                <li v-for="item in topics" :key="item.slug">
                    <Link
                        :href="item.url"
                        :data-testid="`help-topic-${item.slug}`"
                        class="flex h-full flex-col rounded-2xl bg-surface p-4 shadow-sm transition hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold text-ink">
                                <span class="mr-1">{{ item.icon }}</span> {{ item.title }}
                            </p>
                            <span class="shrink-0 rounded-full bg-ink/5 px-2 py-0.5 text-[11px] font-medium text-ink/60">
                                {{ item.audience }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-ink/70">{{ item.teaser }}</p>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
