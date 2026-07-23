<script setup>
import { ref, computed, nextTick } from 'vue';
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
    // Flat { id, path } reassignment targets for this direction.
    options: { type: Array, default: () => [] },
});

const renaming = ref(false);
const addingChild = ref(false);
const confirmingDelete = ref(false);
const moveTo = ref('');

// This node plus all of its descendants, and their total booking count.
function collectIds(node) {
    return [node.id, ...node.children.flatMap(collectIds)];
}
const subtreeIds = computed(() => collectIds(props.node));
function collectBookings(node) {
    return (node.bookings_count ?? 0) + node.children.reduce((sum, c) => sum + collectBookings(c), 0);
}
const subtreeBookings = computed(() => collectBookings(props.node));
// Valid move targets: any same-direction category outside this subtree.
const targetOptions = computed(() => props.options.filter((o) => !subtreeIds.value.includes(o.id)));
const renameValue = ref(props.node.name);
const commentValue = ref(props.node.comment ?? '');
const childName = ref('');
const renameInput = ref(null);
const childInput = ref(null);

async function startRename() {
    renameValue.value = props.node.name;
    commentValue.value = props.node.comment ?? '';
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
        { name, comment: commentValue.value.trim() || null, active: props.node.active },
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
        { name: props.node.name, comment: props.node.comment, active: !props.node.active },
        { preserveScroll: true },
    );
}

function destroy() {
    // With bookings in the subtree, ask where to move them; otherwise a simple confirm.
    if (subtreeBookings.value > 0) {
        moveTo.value = '';
        confirmingDelete.value = true;
        return;
    }
    if (confirm(t('accounting.categories.delete_confirm', { name: props.node.name }))) {
        router.delete(categoriesDestroy(props.node.id).url, { preserveScroll: true });
    }
}

function confirmMove() {
    if (!moveTo.value) {
        return;
    }
    router.delete(categoriesDestroy(props.node.id).url, {
        data: { move_to: moveTo.value },
        preserveScroll: true,
        onSuccess: () => (confirmingDelete.value = false),
    });
}
</script>

<template>
    <li>
        <div
            class="group flex items-start gap-2 rounded-lg py-1.5 pr-1 hover:bg-ink/5"
            :class="{ 'opacity-50': !node.active }"
        >
            <!-- Edit mode (name + AI hint) -->
            <template v-if="renaming">
                <div class="min-w-0 flex-1 space-y-1">
                    <input
                        ref="renameInput"
                        v-model="renameValue"
                        type="text"
                        class="block w-full rounded-md border-ink/20 py-1 text-sm focus:border-hort-teal focus:ring-hort-teal"
                        @keyup.enter="saveRename"
                        @keyup.esc="renaming = false"
                    />
                    <textarea
                        v-model="commentValue"
                        rows="2"
                        :placeholder="$t('accounting.categories.comment_placeholder')"
                        class="block w-full rounded-md border-ink/20 py-1 text-xs focus:border-hort-teal focus:ring-hort-teal"
                    ></textarea>
                </div>
                <button type="button" class="rounded p-1 text-hort-teal-dark hover:bg-hort-teal/10" @click="saveRename">
                    <CheckIcon class="h-4 w-4" />
                </button>
                <button type="button" class="rounded p-1 text-ink/40 hover:bg-ink/10" @click="renaming = false">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </template>

            <!-- Display mode -->
            <template v-else>
                <div class="min-w-0 flex-1">
                    <span class="block truncate text-sm text-ink">
                        {{ node.name }}
                        <span v-if="!node.active" class="ml-1 text-xs text-ink/40">({{ $t('accounting.categories.inactive') }})</span>
                        <span v-if="node.bookings_count" class="ml-1 text-xs text-ink/40">· {{ node.bookings_count }}</span>
                    </span>
                    <span v-if="node.comment" class="block truncate text-xs italic text-ink/40" :title="node.comment">
                        {{ node.comment }}
                    </span>
                </div>
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

        <!-- Reassign the subtree's bookings, then delete -->
        <div v-if="confirmingDelete" class="flex flex-wrap items-center gap-2 py-1.5 pl-4">
            <span class="text-xs text-ink/60">{{ $t('accounting.categories.reassign_prompt', { count: subtreeBookings }) }}</span>
            <select
                v-model="moveTo"
                class="min-w-0 flex-1 rounded-md border-ink/20 py-1 text-sm focus:border-hort-teal focus:ring-hort-teal"
            >
                <option value="">{{ $t('accounting.categories.reassign_placeholder') }}</option>
                <option v-for="o in targetOptions" :key="o.id" :value="o.id">{{ o.path }}</option>
            </select>
            <button
                type="button"
                class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600 transition hover:bg-red-100 disabled:opacity-40"
                :disabled="!moveTo"
                @click="confirmMove"
            >
                {{ $t('accounting.categories.reassign_confirm') }}
            </button>
            <button type="button" class="rounded p-1 text-ink/40 hover:bg-ink/10" @click="confirmingDelete = false">
                <XMarkIcon class="h-4 w-4" />
            </button>
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
                :options="options"
            />
        </ul>
    </li>
</template>
