<script setup>
import { create as childrenCreate, destroy as childrenDestroy } from '@/routes/children';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChildCard from './Partials/ChildCard.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
    children: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    canCreate: {
        type: Boolean,
        default: false,
    },
});

const flash = computed(() => usePage().props.flash?.status);
const flashError = computed(() => usePage().props.flash?.error);

// Active children up top; former ones (already left) sit in a separate section at
// the bottom behind a toggle, sorted by leaving date (most recent first).
const showFormer = ref(false);
const activeChildren = computed(() => props.children.filter((c) => c.active));
const formerChildren = computed(() =>
    props.children
        .filter((c) => !c.active)
        .sort((a, b) => (b.active_until ?? '').localeCompare(a.active_until ?? '')),
);

function destroy(child) {
    if (
        confirm(t('children.delete_confirm', { name: child.name }))
    ) {
        router.delete(childrenDestroy(child.id).url);
    }
}
</script>

<template>
    <Head :title="$t('children.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">
                {{ canManage ? $t('children.title') : $t('children.my_children') }}
            </h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>
            <div
                v-if="flashError"
                class="rounded-2xl bg-red-500/10 px-4 py-3 text-sm font-medium text-red-700 dark:text-red-400"
            >
                {{ flashError }}
            </div>

            <Link
                v-if="canCreate"
                :href="childrenCreate().url"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-hort-teal px-6 py-4 text-base font-semibold text-hort-navy shadow-sm transition hover:bg-hort-teal-dark active:scale-[0.99]"
            >
                <span class="text-xl leading-none">+</span> {{ $t('children.add_child') }}
            </Link>

            <ul v-if="activeChildren.length" class="space-y-3">
                <ChildCard v-for="child in activeChildren" :key="child.id" :child="child" @delete="destroy" />
            </ul>

            <p
                v-else
                class="rounded-2xl border-2 border-dashed border-ink/15 p-6 text-center text-sm text-ink/50"
            >
                <template v-if="canManage">
                    {{ $t('children.empty_manage') }}
                </template>
                <template v-else>
                    {{ $t('children.empty_parent') }}
                </template>
            </p>

            <!-- Former children — separated at the very bottom, behind a toggle. -->
            <div v-if="formerChildren.length" class="border-t border-ink/10 pt-4">
                <button
                    type="button"
                    class="w-full text-center text-sm text-ink/50 transition hover:text-ink"
                    @click="showFormer = !showFormer"
                >
                    {{ showFormer ? $t('children.hide_former') : $t('children.show_former', { n: formerChildren.length }) }}
                </button>

                <ul v-if="showFormer" class="mt-4 space-y-3">
                    <ChildCard v-for="child in formerChildren" :key="child.id" :child="child" @delete="destroy" />
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
