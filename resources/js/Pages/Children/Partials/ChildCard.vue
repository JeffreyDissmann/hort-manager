<script setup>
import { Link } from '@inertiajs/vue3';
import { TrashIcon } from '@heroicons/vue/24/outline';
import { edit as childrenEdit } from '@/routes/children';

defineProps({
    child: { type: Object, required: true },
});
const emit = defineEmits(['delete']);

function formatDate(value) {
    if (!value) {
        return null;
    }
    const [year, month, day] = value.split('-');
    return `${day}.${month}.${year}`;
}
</script>

<template>
    <li class="rounded-2xl bg-surface p-4 shadow-sm" :class="{ 'opacity-60': !child.active }">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate font-semibold text-ink">
                    {{ child.name }}
                    <span
                        v-if="!child.active"
                        class="ml-1 rounded bg-ink/10 px-1.5 py-0.5 text-xs font-medium text-ink/50"
                    >
                        {{ $t('children.former_badge') }}<template v-if="child.active_until"> · {{ $t('children.until', { date: formatDate(child.active_until) }) }}</template>
                    </span>
                </p>
                <p v-if="formatDate(child.date_of_birth)" class="mt-0.5 text-sm text-ink/50">
                    * {{ formatDate(child.date_of_birth) }}
                </p>
                <p v-if="child.note" class="mt-1 line-clamp-2 text-sm text-ink/70">
                    {{ child.note }}
                </p>
                <p v-if="child.guardians.length" class="mt-1 flex flex-wrap items-center gap-1">
                    <span class="text-xs text-ink/40">{{ $t('children.parents_title') }}:</span>
                    <span
                        v-for="name in child.guardians"
                        :key="name"
                        class="rounded bg-hort-teal/15 px-1.5 py-0.5 text-xs font-medium text-hort-teal-dark"
                    >
                        {{ name }}
                    </span>
                </p>
            </div>
            <button
                v-if="child.can_delete"
                type="button"
                @click="emit('delete', child)"
                class="shrink-0 rounded-lg p-2 text-ink/30 transition hover:bg-red-50 hover:text-red-600"
                :aria-label="$t('children.delete_child')"
            >
                <TrashIcon class="h-5 w-5" />
            </button>
        </div>

        <Link
            :href="childrenEdit(child.id).url"
            class="mt-3 flex items-center justify-center gap-1 rounded-xl border-2 border-ink/10 py-2.5 text-sm font-semibold text-ink transition hover:border-hort-teal hover:bg-hort-teal/10"
        >
            {{ $t('children.edit_schedule') }}
        </Link>
    </li>
</template>
