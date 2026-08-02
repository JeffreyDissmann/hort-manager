<script setup>
// One offered Ferienbetreuung day and its Betreuungszeit. Read-only for parents;
// staff adjust the times here and remove the day entirely. What *happens* that day
// (Aktivität, Essen) is edited on /program, like any other Hort day.
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TimeSelect from '@/Components/TimeSelect.vue';
import { update as careDayUpdate, destroy as careDayDestroy } from '@/routes/care-days';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    day: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const locale = computed(() => usePage().props.locale || 'de');

const editing = ref(false);
const confirmingRemove = ref(false);
const saving = ref(false);
const draft = ref({ starts_at: '', ends_at: '' });

function open() {
    draft.value = { starts_at: props.day.starts_at, ends_at: props.day.ends_at };
    editing.value = true;
}

function save() {
    saving.value = true;
    router.patch(careDayUpdate(props.day.id).url, draft.value, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
        onFinish: () => (saving.value = false),
    });
}

function remove() {
    router.delete(careDayDestroy(props.day.id).url, {
        preserveScroll: true,
        onFinish: () => (confirmingRemove.value = false),
    });
}

const dayLabel = computed(() =>
    new Date(`${props.day.date}T00:00:00`).toLocaleDateString(locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }),
);
</script>

<template>
    <li :data-testid="`care-day-${day.id}`" class="py-2 first:pt-0 last:pb-0">
        <div v-if="!editing" class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="w-24 shrink-0 text-sm font-medium text-ink">{{ dayLabel }}</span>
            <span class="text-sm text-ink/70">{{ day.starts_at }}–{{ day.ends_at }}</span>

            <div v-if="canManage" class="ml-auto flex items-center gap-2">
                <template v-if="confirmingRemove">
                    <span class="text-xs text-ink/60">{{ $t('care.remove_confirm') }}</span>
                    <DangerButton :data-testid="`care-day-remove-confirm-${day.id}`" @click="remove">
                        {{ $t('common.delete') }}
                    </DangerButton>
                    <SecondaryButton @click="confirmingRemove = false">
                        {{ $t('common.cancel') }}
                    </SecondaryButton>
                </template>
                <template v-else>
                    <SecondaryButton :data-testid="`care-day-edit-${day.id}`" @click="open">
                        {{ $t('common.edit') }}
                    </SecondaryButton>
                    <SecondaryButton @click="confirmingRemove = true">
                        {{ $t('care.remove_day') }}
                    </SecondaryButton>
                </template>
            </div>
        </div>

        <div v-else class="flex flex-wrap items-center gap-2">
            <span class="w-24 shrink-0 text-sm font-medium text-ink">{{ dayLabel }}</span>
            <!-- Each TimeSelect holds an hour *and* a minute dropdown, so it needs room
                 for two; anything narrower clips them to a sliver. -->
            <TimeSelect v-model="draft.starts_at" from="06:00" :test-id="`care-start-${day.id}`" class="w-44 shrink-0" />
            <span class="text-ink/40">–</span>
            <TimeSelect v-model="draft.ends_at" from="06:00" :test-id="`care-end-${day.id}`" class="w-44 shrink-0" />
            <PrimaryButton :data-testid="`care-day-save-${day.id}`" :disabled="saving" @click="save">
                {{ $t('common.save') }}
            </PrimaryButton>
            <SecondaryButton @click="editing = false">{{ $t('common.cancel') }}</SecondaryButton>
        </div>
    </li>
</template>
