<script setup>
// „Datenpflege" — the gaps someone can go and close. Admin-only, and deliberately
// not tied to a Zeitraum: a missing Stammplan is missing now or not at all.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { index as childrenIndex } from '@/routes/children';
import { index as excursionsIndex } from '@/routes/excursions';
import { index as usersIndex } from '@/routes/users';
import { t } from '@/i18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // { check => count } as things stand today.
    gaps: { type: Object, default: () => ({}) },
    // What is stored and since when — the case for an Aufbewahrungsfrist.
    inventory: { type: Object, default: () => ({}) },
});

const locale = computed(() => usePage().props.locale || 'de');

/** „8. Februar 2026", or a dash when the table is empty. */
function dateLabel(date) {
    return date
        ? new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        })
        : '–';
}

/** Bytes as MB — the number that says what a backup costs. */
const databaseSize = computed(() => {
    const bytes = props.inventory.database_bytes;

    return bytes
        ? `${(bytes / 1024 / 1024).toLocaleString(locale.value, { maximumFractionDigits: 1 })} MB`
        : '–';
});

// Each check points at the page where it gets fixed — a count with nowhere to go is
// just another number.
// „failed_jobs" has no page to send anyone to — it is cleared from the command line,
// so it states its count and stops there.
const checks = computed(() => [
    { key: 'children_without_plan', href: childrenIndex().url },
    { key: 'children_without_guardian', href: childrenIndex().url },
    { key: 'guardians_without_slack', href: usersIndex().url },
    { key: 'orphaned_accounts', href: usersIndex().url },
    { key: 'failed_jobs', href: null },
    { key: 'open_excursion_answers', href: excursionsIndex().url },
].map((check) => ({ ...check, count: props.gaps[check.key] ?? 0 })));

const allClear = computed(() => checks.value.every((check) => check.count === 0));

// The three numbers that don't belong in the table: they count things, not records.
const headline = computed(() => [
    {
        label: t('data_upkeep.children_count'),
        value: `${props.inventory.children_active ?? 0} (+${props.inventory.children_former ?? 0})`,
    },
    {
        label: t('data_upkeep.users_count'),
        value: `${props.inventory.users ?? 0} (${props.inventory.users_with_slack ?? 0} Slack)`,
    },
    { label: t('data_upkeep.database_size'), value: databaseSize.value },
]);
</script>

<template>
    <Head :title="$t('data_upkeep.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-ink">{{ $t('data_upkeep.title') }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <section class="rounded-2xl bg-surface p-4 shadow-sm sm:p-6" data-testid="gaps">
                    <p class="text-sm text-ink/60">{{ $t('data_upkeep.intro') }}</p>

                    <p v-if="allClear" class="mt-4 text-sm font-medium text-hort-teal-dark">
                        ✅ {{ $t('data_upkeep.clear') }}
                    </p>

                    <ul v-else class="mt-3 divide-y divide-ink/5">
                        <!-- A check that is fine keeps its row: „0 offene Antworten" is
                             information, and a list that shrinks is hard to trust. -->
                        <li
                            v-for="check in checks"
                            :key="check.key"
                            class="flex items-center justify-between gap-3 py-2.5 text-sm"
                        >
                            <span :class="check.count ? 'text-ink' : 'text-ink/40'">
                                {{ $t(`data_upkeep.check.${check.key}`) }}
                            </span>
                            <Link
                                v-if="check.count && check.href"
                                :href="check.href"
                                :data-testid="`check-${check.key}`"
                                class="shrink-0 rounded-lg bg-hort-orange/15 px-2.5 py-1 font-semibold tabular-nums text-hort-orange-dark transition hover:bg-hort-orange/25"
                            >
                                {{ check.count }} →
                            </Link>
                            <span
                                v-else-if="check.count"
                                class="shrink-0 rounded-lg bg-hort-orange/15 px-2.5 py-1 font-semibold tabular-nums text-hort-orange-dark"
                            >
                                {{ check.count }}
                            </span>
                            <span v-else class="shrink-0 px-2.5 py-1 tabular-nums text-ink/30">0</span>
                        </li>
                    </ul>
                </section>

                <!-- What is piling up, and since when — the case for an
                     Aufbewahrungsfrist rather than an assertion that one is needed. -->
                <section class="mt-6 rounded-2xl bg-surface p-4 shadow-sm sm:p-6" data-testid="inventory">
                    <h3 class="font-semibold text-ink">{{ $t('data_upkeep.inventory_title') }}</h3>
                    <p class="mt-0.5 text-sm text-ink/60">{{ $t('data_upkeep.inventory_intro') }}</p>

                    <table class="mt-3 w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-ink/40">
                                <th class="py-2 font-medium">{{ $t('data_upkeep.inventory_what') }}</th>
                                <th class="py-2 text-right font-medium">{{ $t('data_upkeep.inventory_count') }}</th>
                                <th class="py-2 text-right font-medium">{{ $t('data_upkeep.inventory_oldest') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/5">
                            <tr v-for="record in inventory.records" :key="record.key">
                                <td class="py-2 text-ink">{{ $t(`data_upkeep.record.${record.key}`) }}</td>
                                <td class="py-2 text-right tabular-nums text-ink">{{ record.count.toLocaleString(locale) }}</td>
                                <td class="py-2 text-right text-ink/60">{{ dateLabel(record.oldest) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div v-for="stat in headline" :key="stat.label" class="rounded-xl bg-canvas px-3 py-2">
                            <dt class="text-xs text-ink/50">{{ stat.label }}</dt>
                            <dd class="text-base font-semibold tabular-nums text-ink">{{ stat.value }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
