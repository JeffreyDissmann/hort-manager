<script setup>
import { ref, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    store as categoriesStore,
    update as categoriesUpdate,
    destroy as categoriesDestroy,
} from '@/routes/accounting/categories';
import { t } from '@/i18n';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    EyeIcon,
    EyeSlashIcon,
    CheckIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

defineOptions({ name: 'CategoryNode' });

const props = defineProps({
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
});

const renaming = ref(false);
const addingChild = ref(false);
const renameValue = ref(props.node.name);
const childName = ref('');
const renameInput = ref(null);
const childInput = ref(null);

async function startRename() {
    renameValue.value = props.node.name;
    renaming.value = true;
    await nextTick();
    renameInput.value?.focus();
}

function saveRename() {
    const name = renameValue.value.trim();
    if (!name) {
        renaming.value = false;
        return;
    }
    router.patch(
        categoriesUpdate(props.node.id).url,
        { name, active: props.node.active },
        { preserveScroll: true, onSuccess: () => (renaming.value = false) },
    );
}

async function startAddChild() {
    childName.value = '';
    addingChild.value = true;
    await nextTick();
    childInput.value?.focus();
}

function saveChild() {
    const name = childName.value.trim();
    if (!name) {
        addingChild.value = false;
        return;
    }
    router.post(
        categoriesStore().url,
        { name, parent_id: props.node.id },
        { preserveScroll: true, onSuccess: () => (addingChild.value = false) },
    );
}

function toggleActive() {
    router.patch(
        categoriesUpdate(props.node.id).url,
        { name: props.node.name, active: !props.node.active },
        { preserveScroll: true },
    );
}

function destroy() {
    if (confirm(t('accounting.categories.delete_confirm', { name: props.node.name }))) {
        router.delete(categoriesDestroy(props.node.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <li>
        <div
            class="group flex items-center gap-2 rounded-lg py-1.5 pr-1 hover:bg-ink/5"
            :class="{ 'opacity-50': !node.active }"
        >
            <!-- Rename mode -->
            <template v-if="renaming">
                <input
                    ref="renameInput"
                    v-model="renameValue"
                    type="text"
                    class="min-w-0 flex-1 rounded-md border-ink/20 py-1 text-sm focus:border-hort-teal focus:ring-hort-teal"
                    @keyup.enter="saveRename"
                    @keyup.esc="renaming = false"
                />
                <button type="button" class="rounded p-1 text-hort-teal-dark hover:bg-hort-teal/10" @click="saveRename">
                    <CheckIcon class="h-4 w-4" />
                </button>
                <button type="button" class="rounded p-1 text-ink/40 hover:bg-ink/10" @click="renaming = false">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </template>

            <!-- Display mode -->
            <template v-else>
                <span class="min-w-0 flex-1 truncate text-sm text-ink">
                    {{ node.name }}
                    <span v-if="!node.active" class="ml-1 text-xs text-ink/40">({{ $t('accounting.categories.inactive') }})</span>
                    <span v-if="node.bookings_count" class="ml-1 text-xs text-ink/40">· {{ node.bookings_count }}</span>
                </span>
                <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                    <button
                        type="button"
                        class="rounded p-1 text-ink/50 hover:bg-ink/10 hover:text-ink"
                        :title="$t('accounting.categories.add_child')"
                        @click="startAddChild"
                    >
                        <PlusIcon class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded p-1 text-ink/50 hover:bg-ink/10 hover:text-ink"
                        :title="$t('accounting.categories.rename')"
                        @click="startRename"
                    >
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded p-1 text-ink/50 hover:bg-ink/10 hover:text-ink"
                        :title="node.active ? $t('accounting.categories.deactivate') : $t('accounting.categories.activate')"
                        @click="toggleActive"
                    >
                        <EyeSlashIcon v-if="node.active" class="h-4 w-4" />
                        <EyeIcon v-else class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded p-1 text-ink/50 hover:bg-red-50 hover:text-red-600"
                        :title="$t('common.delete')"
                        @click="destroy"
                    >
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </div>

        <!-- Add-child input -->
        <div v-if="addingChild" class="flex items-center gap-2 py-1.5 pl-4">
            <input
                ref="childInput"
                v-model="childName"
                type="text"
                :placeholder="$t('accounting.categories.name_placeholder')"
                class="min-w-0 flex-1 rounded-md border-ink/20 py-1 text-sm focus:border-hort-teal focus:ring-hort-teal"
                @keyup.enter="saveChild"
                @keyup.esc="addingChild = false"
            />
            <button type="button" class="rounded p-1 text-hort-teal-dark hover:bg-hort-teal/10" @click="saveChild">
                <CheckIcon class="h-4 w-4" />
            </button>
            <button type="button" class="rounded p-1 text-ink/40 hover:bg-ink/10" @click="addingChild = false">
                <XMarkIcon class="h-4 w-4" />
            </button>
        </div>

        <!-- Children -->
        <ul v-if="node.children.length" class="ml-4 border-l border-ink/10 pl-2">
            <CategoryNode
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :depth="depth + 1"
            />
        </ul>
    </li>
</template>
