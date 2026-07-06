<script setup>
import { index as childrenIndex } from '@/routes/children';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Timetable from '@/Components/Timetable.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/i18n';

defineProps({
    standard: { type: Array, default: () => [] },
});

const isStaff = computed(() => usePage().props.auth?.user?.role === 'staff');
const columns = ['mon', 'tue', 'wed', 'thu', 'fri'].map((key) => ({
    label: t(`weekly.weekday.${key}`),
}));
</script>

<template>
    <Head :title="$t('nav.standard_plan')" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">{{ $t('nav.standard_plan') }}</h2>
        </template>

        <div class="space-y-4">
            <p class="text-sm text-hort-navy/60">
                {{ $t('weekly.standard_intro') }}
                <Link
                    :href="childrenIndex().url"
                    class="font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                >
                    {{ isStaff ? $t('weekly.edit_link_staff') : $t('weekly.edit_link_parent') }}
                </Link>
            </p>

            <Timetable v-if="standard.length" :rows="standard" :columns="columns" />

            <p
                v-else
                class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
            >
                {{ $t('weekly.empty_standard') }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
