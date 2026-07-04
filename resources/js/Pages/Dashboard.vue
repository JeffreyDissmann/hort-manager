<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { board, weeklyPlan, program, help } from '@/routes';
import { index as childrenIndex } from '@/routes/children';
import { index as excursionsIndex } from '@/routes/excursions';
import { index as pollsIndex } from '@/routes/polls';
import { t } from '@/i18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? '');
const isStaff = computed(() => user.value?.role === 'staff');

const tiles = computed(() => {
    const items = [
        {
            title: t('dashboard.today'),
            text: isStaff.value
                ? t('dashboard.today_text_staff')
                : t('dashboard.today_text_parent'),
            href: board().url,
        },
        {
            title: t('dashboard.excursions'),
            text: isStaff.value
                ? t('dashboard.excursions_text_staff')
                : t('dashboard.excursions_text_parent'),
            href: isStaff.value ? excursionsIndex().url : pollsIndex().url,
        },
        {
            title: t('dashboard.weekly_plan'),
            text: t('dashboard.weekly_plan_text'),
            href: weeklyPlan().url,
        },
    ];

    if (isStaff.value) {
        items.push({
            title: t('dashboard.program'),
            text: t('dashboard.program_text'),
            href: program().url,
        });
    }

    items.push({
        title: isStaff.value ? t('dashboard.children_staff') : t('dashboard.children_parent'),
        text: isStaff.value
            ? t('dashboard.children_text_staff')
            : t('dashboard.children_text_parent'),
        href: childrenIndex().url,
    });

    return items;
});
</script>

<template>
    <Head :title="$t('dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">
                {{ $t('dashboard.greeting', { name: userName }) }}
            </h2>
        </template>

        <div class="space-y-3">
            <Link
                v-for="tile in tiles"
                :key="tile.title"
                :href="tile.href"
                class="flex items-center justify-between rounded-2xl bg-white p-5 shadow-sm transition hover:shadow active:scale-[0.99]"
            >
                <div>
                    <h3 class="font-semibold text-hort-navy">{{ tile.title }}</h3>
                    <p class="mt-1 text-sm text-hort-navy/60">{{ tile.text }}</p>
                </div>
                <span class="text-2xl text-hort-teal-dark">→</span>
            </Link>
        </div>

        <p class="mt-6 text-center text-sm text-hort-navy/60">
            {{ $t('dashboard.new_here') }}
            <Link :href="help().url" class="font-medium text-hort-teal-dark underline hover:text-hort-navy">
                {{ $t('dashboard.how_it_works') }}
            </Link>
        </p>
    </AuthenticatedLayout>
</template>
