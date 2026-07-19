<script setup>
import { computed, ref, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { store as categoriesStore, update as categoriesUpdate } from '@/routes/accounting/categories';
import { t } from '@/i18n';
import { PlusIcon, CheckIcon, XMarkIcon, ChatBubbleBottomCenterTextIcon } from '@heroicons/vue/24/outline';

// A grouped category <select> (Einnahmen / Ausgaben, indented by depth) with an
// inline „neue Kategorie" affordance so a missing category can be added without
// leaving the booking form. Inline-add reloads only the shared `categories` prop
// (form state preserved) and auto-selects the freshly created node.
const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    categories: { type: Array, required: true },
    // When set ('income'|'expense'), only that direction is offered.
    direction: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue']);

const adding = ref(false);
const newName = ref('');
const newDirection = ref(props.direction ?? 'expense');
const nameInput = ref(null);

// The currently-selected category option (for editing its AI hint inline).
const selected = computed(() => props.categories.find((c) => c.id === Number(props.modelValue)) ?? null);

const editingHint = ref(false);
const hintValue = ref('');
const hintInput = ref(null);

async function startEditHint() {
    hintValue.value = selected.value?.comment ?? '';
    editingHint.value = true;
    await nextTick();
    hintInput.value?.focus();
}

function saveHint() {
    const cat = selected.value;
    if (!cat) {
        editingHint.value = false;
        return;
    }
    router.patch(
        categoriesUpdate(cat.id).url,
        { name: cat.name, comment: hintValue.value.trim() || null, active: cat.active },
        { preserveScroll: true, preserveState: true, only: ['categories'], onSuccess: () => (editingHint.value = false) },
    );
}

const groups = computed(() =>
    [
        { direction: 'income', label: t('accounting.categories.income') },
        { direction: 'expense', label: t('accounting.categories.expense') },
    ].filter((g) => !props.direction || g.direction === props.direction),
);

function optionsFor(direction) {
    return props.categories.filter((c) => c.direction === direction);
}

// Indent nested options with figure spaces so the tree shape reads in a native select.
function indent(depth) {
    return '  '.repeat(depth);
}

async function startAdd() {
    newName.value = '';
    adding.value = true;
    await nextTick();
    nameInput.value?.focus();
}

function saveNew() {
    const name = newName.value.trim();
    if (!name) {
        adding.value = false;
        return;
    }
    const before = new Set(props.categories.map((c) => c.id));
    router.post(
        categoriesStore().url,
        { name, direction: newDirection.value },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['categories'],
            onSuccess: () => {
                adding.value = false;
                // usePage().props.categories is now the refreshed list.
                const fresh = usePage().props.categories ?? [];
                const added = fresh.find((c) => !before.has(c.id));
                if (added) {
                    emit('update:modelValue', added.id);
                }
            },
        },
    );
}
</script>

<template>
    <div>
        <div class="flex items-center gap-2">
            <select
                :value="modelValue ?? ''"
                class="min-w-0 flex-1 rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                @change="emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)"
            >
                <option value="">{{ $t('accounting.bookings.pick_category') }}</option>
                <optgroup v-for="group in groups" :key="group.direction" :label="group.label">
                    <option v-for="option in optionsFor(group.direction)" :key="option.id" :value="option.id">
                        {{ indent(option.depth) }}{{ option.name }}
                    </option>
                </optgroup>
            </select>
            <button
                v-if="selected"
                type="button"
                class="shrink-0 rounded-lg bg-ink/5 p-2 text-ink/60 transition hover:bg-ink/10 hover:text-ink"
                :class="{ 'text-hort-teal-dark': selected.comment }"
                :title="$t('accounting.categories.edit_hint')"
                @click="startEditHint"
            >
                <ChatBubbleBottomCenterTextIcon class="h-5 w-5" />
            </button>
            <button
                type="button"
                class="shrink-0 rounded-lg bg-ink/5 p-2 text-ink/60 transition hover:bg-ink/10 hover:text-ink"
                :title="$t('accounting.bookings.add_category')"
                @click="startAdd"
            >
                <PlusIcon class="h-5 w-5" />
            </button>
        </div>

        <!-- Edit the selected category's AI hint -->
        <div v-if="editingHint && selected" class="mt-2 rounded-lg bg-ink/5 p-2">
            <p class="mb-1 text-xs font-medium text-ink/50">
                {{ $t('accounting.categories.edit_hint') }}: {{ selected.path }}
            </p>
            <div class="flex items-start gap-2">
                <textarea
                    ref="hintInput"
                    v-model="hintValue"
                    rows="2"
                    :placeholder="$t('accounting.categories.comment_placeholder')"
                    class="min-w-0 flex-1 rounded-md border-ink/20 py-1 text-xs focus:border-hort-teal focus:ring-hort-teal"
                    @keyup.esc="editingHint = false"
                ></textarea>
                <button type="button" class="rounded p-1 text-hort-teal-dark hover:bg-hort-teal/10" @click="saveHint">
                    <CheckIcon class="h-4 w-4" />
                </button>
                <button type="button" class="rounded p-1 text-ink/40 hover:bg-ink/10" @click="editingHint = false">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Inline add -->
        <div v-if="adding" class="mt-2 flex flex-wrap items-center gap-2 rounded-lg bg-ink/5 p-2">
            <input
                ref="nameInput"
                v-model="newName"
                type="text"
                :placeholder="$t('accounting.bookings.new_category')"
                class="min-w-0 flex-1 rounded-md border-ink/20 py-1 text-sm focus:border-hort-teal focus:ring-hort-teal"
                @keyup.enter="saveNew"
                @keyup.esc="adding = false"
            />
            <select
                v-if="!direction"
                v-model="newDirection"
                class="rounded-md border-ink/20 py-1 text-sm focus:border-hort-teal focus:ring-hort-teal"
            >
                <option value="income">{{ $t('accounting.categories.income') }}</option>
                <option value="expense">{{ $t('accounting.categories.expense') }}</option>
            </select>
            <button type="button" class="rounded p-1 text-hort-teal-dark hover:bg-hort-teal/10" @click="saveNew">
                <CheckIcon class="h-4 w-4" />
            </button>
            <button type="button" class="rounded p-1 text-ink/40 hover:bg-ink/10" @click="adding = false">
                <XMarkIcon class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
