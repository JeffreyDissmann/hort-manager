<script setup>
import { reactive, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryNode from './Partials/CategoryNode.vue';
import { store as categoriesStore } from '@/routes/accounting/categories';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    // { income: [...tree], expense: [...tree] }
    trees: { type: Object, required: true },
});

// Flat "Parent › Child" options per direction — the reassignment targets when a
// category being deleted still carries bookings.
function flatten(nodes, prefix = '') {
    return nodes.flatMap((n) => {
        const path = prefix ? `${prefix} › ${n.name}` : n.name;
        return [{ id: n.id, path }, ...flatten(n.children, path)];
    });
}
const options = computed(() => ({
    income: flatten(props.trees.income),
    expense: flatten(props.trees.expense),
}));

const sections = [
    { key: 'income', accent: 'text-hort-teal-dark' },
    { key: 'expense', accent: 'text-red-600' },
];

const rootName = reactive({ income: '', expense: '' });

function addRoot(direction) {
    const name = rootName[direction].trim();
    if (!name) {
        return;
    }
    router.post(
        categoriesStore().url,
        { name, direction },
        { preserveScroll: true, onSuccess: () => (rootName[direction] = '') },
    );
}
</script>

<template>
    <Head :title="$t('accounting.categories.title')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">
                {{ $t('accounting.title') }}
            </p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.categories.title') }}</h2>
        </template>

        <div class="mx-auto max-w-4xl">
            <p class="mb-4 text-sm text-ink/60">{{ $t('accounting.categories.intro') }}</p>

            <div class="grid gap-4 md:grid-cols-2">
                <section
                    v-for="section in sections"
                    :key="section.key"
                    class="rounded-2xl bg-surface p-5 shadow-sm"
                >
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide" :class="section.accent">
                        {{ $t(`accounting.categories.${section.key}`) }}
                    </h3>

                    <ul v-if="trees[section.key].length" class="mb-3">
                        <CategoryNode
                            v-for="node in trees[section.key]"
                            :key="node.id"
                            :node="node"
                            :options="options[section.key]"
                        />
                    </ul>
                    <p v-else class="mb-3 text-sm text-ink/40">{{ $t('accounting.categories.empty') }}</p>

                    <div class="flex items-center gap-2 border-t border-ink/10 pt-3">
                        <input
                            v-model="rootName[section.key]"
                            type="text"
                            :placeholder="$t('accounting.categories.new_root')"
                            class="min-w-0 flex-1 rounded-md border-ink/20 py-1.5 text-sm focus:border-hort-teal focus:ring-hort-teal"
                            @keyup.enter="addRoot(section.key)"
                        />
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-lg bg-ink/5 px-3 py-1.5 text-sm font-medium text-ink transition hover:bg-ink/10"
                            @click="addRoot(section.key)"
                        >
                            <PlusIcon class="h-4 w-4" />
                            {{ $t('common.add') }}
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
