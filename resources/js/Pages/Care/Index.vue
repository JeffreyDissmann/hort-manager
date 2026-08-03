<script setup>
// The staff sign-up screen: every child, every open Ferienbetreuung, editable past
// the Anmeldeschluss. Parents do the same thing on „Ausflüge & Ferien" and are
// redirected there — this page exists for what only staff may do.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CareSignupList from '@/Components/Care/SignupList.vue';
import { help } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    children: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    canOverrideDeadline: { type: Boolean, default: false },
});

const flash = computed(() => usePage().props.flash?.status);
</script>

<template>
    <Head :title="$t('care.heading')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-xl font-semibold text-ink">{{ $t('care.heading') }}</h2>
                <Link
                    :href="help({ topic: 'holidays' }).url"
                    class="text-sm font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                >
                    {{ $t('care.how_it_works') }}
                </Link>
            </div>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <p v-if="!periods.length" class="rounded-2xl bg-surface p-6 text-center text-sm text-ink/50">
                {{ $t('care.none') }}
            </p>

            <CareSignupList
                :children="children"
                :periods="periods"
                :can-override-deadline="canOverrideDeadline"
            />
        </div>
    </AuthenticatedLayout>
</template>
