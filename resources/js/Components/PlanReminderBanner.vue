<script setup>
// Warns a parent when one of their children has no Stammplan yet, linking straight
// to that child's schedule editor. Fed by the shared `childrenWithoutPlan` prop.
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { edit as childrenEdit } from '@/routes/children';

const children = computed(() => usePage().props.childrenWithoutPlan ?? []);
</script>

<template>
    <div
        v-if="children.length"
        class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800"
    >
        <p class="font-semibold">⚠️ {{ $t('plan_reminder.title') }}</p>
        <p class="mt-0.5">{{ $t('plan_reminder.body') }}</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <Link
                v-for="child in children"
                :key="child.id"
                :href="childrenEdit(child.id).url"
                class="rounded-lg bg-amber-600 px-3 py-1.5 font-semibold text-white transition hover:bg-amber-700"
            >
                {{ $t('plan_reminder.set_for', { name: child.name }) }}
            </Link>
        </div>
    </div>
</template>
