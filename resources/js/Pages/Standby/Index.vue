<script setup>
// Springerdienst: the month list of which family stands by when Erzieher are ill.
// Everyone reads it (own days highlighted); the coordinator picks a child per slot.
// All edits — by hand or from the fair random proposal — collect in a draft and are
// saved together, so a proposal can be adjusted before anything is stored.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { index as standbyIndex, update as standbyUpdate } from '@/routes/standby';
import { ChevronLeftIcon, ChevronRightIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    days: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    children: { type: Array, default: () => [] },
    counts: { type: [Object, Array], default: () => ({}) },
    proposal: { type: Array, default: null },
});

const flash = computed(() => usePage().props.flash?.status);
const locale = computed(() => usePage().props.locale || 'de');

const slotKey = (date, slot) => `${date}#${slot}`;

// Draft: slot key → child id (null = clear). Seeded from a proposal when one arrives.
const draft = ref({});
const proposedKeys = ref(new Set());

function seedFromProposal() {
    draft.value = {};
    proposedKeys.value = new Set();
    for (const p of props.proposal ?? []) {
        draft.value[slotKey(p.date, p.slot)] = p.child_id;
        proposedKeys.value.add(slotKey(p.date, p.slot));
    }
}
seedFromProposal();
watch(() => props.proposal, seedFromProposal);

const savedChildId = (day, slot) => slot.child?.id ?? null;
const shownChildId = (day, slot) => {
    const key = slotKey(day.date, slot.slot);
    return key in draft.value ? draft.value[key] : savedChildId(day, slot);
};

function choose(day, slot, value) {
    const key = slotKey(day.date, slot.slot);
    const childId = value === '' ? null : Number(value);
    if (childId === savedChildId(day, slot)) {
        delete draft.value[key];
    } else {
        draft.value[key] = childId;
    }
    proposedKeys.value.delete(key);
}

const pendingCount = computed(() => Object.keys(draft.value).length);

const countFor = (id) => props.counts?.[id] ?? 0;

const saving = ref(false);
function save() {
    const assignments = Object.entries(draft.value).map(([key, childId]) => {
        const [date, slot] = key.split('#');
        return { date, slot: Number(slot), child_id: childId };
    });
    saving.value = true;
    router.put(standbyUpdate().url, { assignments }, {
        preserveScroll: true,
        onSuccess: () => {
            draft.value = {};
            proposedKeys.value = new Set();
        },
        onFinish: () => (saving.value = false),
    });
}

function discard() {
    // Drop the proposal from the URL too, so a reload doesn't bring it back.
    router.get(standbyIndex({ query: { month: props.month.value } }).url, {}, { preserveScroll: true });
}

function propose() {
    router.get(standbyIndex({ query: { month: props.month.value, propose: 1 } }).url, {}, { preserveScroll: true });
}

function goTo(month) {
    router.get(standbyIndex({ query: month ? { month } : {} }).url, {}, { preserveScroll: true });
}

const dayLabel = (date) =>
    new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, { weekday: 'short', day: '2-digit', month: '2-digit' });

const openFillableCount = computed(
    () => props.days.filter((d) => !d.past).flatMap((d) => d.slots).filter((s) => !s.child).length,
);
</script>

<template>
    <Head :title="$t('standby.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('standby.title') }}</h2>
        </template>

        <div class="space-y-4 pb-24">
            <div v-if="flash" class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink">
                {{ flash }}
            </div>

            <p class="text-sm text-ink/60">{{ $t('standby.intro') }}</p>

            <!-- Month navigation -->
            <div class="flex items-center justify-between gap-2 rounded-2xl bg-surface p-2 shadow-sm">
                <button
                    type="button"
                    class="rounded-lg p-2 text-ink/60 transition hover:bg-ink/5 active:scale-95"
                    :aria-label="$t('standby.prev_month')"
                    data-testid="standby-prev"
                    @click="goTo(month.prev)"
                >
                    <ChevronLeftIcon class="h-5 w-5" />
                </button>
                <div class="text-center">
                    <p class="text-sm font-bold text-ink" data-testid="standby-month">{{ month.label }}</p>
                    <button
                        v-if="!month.is_current"
                        type="button"
                        class="text-xs font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                        @click="goTo(null)"
                    >
                        {{ $t('standby.this_month') }}
                    </button>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-ink/60 transition hover:bg-ink/5 active:scale-95"
                    :aria-label="$t('standby.next_month')"
                    data-testid="standby-next"
                    @click="goTo(month.next)"
                >
                    <ChevronRightIcon class="h-5 w-5" />
                </button>
            </div>

            <!-- Coordinator: fair random fill -->
            <div v-if="canManage" class="flex flex-wrap items-center gap-3 rounded-2xl bg-surface p-4 shadow-sm">
                <SecondaryButton :disabled="openFillableCount === 0" data-testid="standby-fill" @click="propose">
                    <SparklesIcon class="mr-1 h-4 w-4" /> {{ $t('standby.fill') }}
                </SecondaryButton>
                <p class="min-w-0 flex-1 text-xs text-ink/50">
                    {{ openFillableCount === 0 ? $t('standby.fill_empty') : $t('standby.fill_hint') }}
                </p>
            </div>

            <!-- The list -->
            <div class="overflow-hidden rounded-2xl bg-surface shadow-sm">
                <p v-if="!days.length" class="p-6 text-center text-sm text-ink/50">{{ $t('standby.no_days') }}</p>
                <ul v-else class="divide-y divide-ink/5">
                    <li
                        v-for="day in days"
                        :key="day.date"
                        class="flex flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-center"
                        :class="{ 'opacity-50': day.past }"
                        :data-testid="`standby-day-${day.date}`"
                    >
                        <div class="flex w-40 shrink-0 items-center gap-2">
                            <span class="text-sm font-medium tabular-nums text-ink">{{ dayLabel(day.date) }}</span>
                            <span v-if="day.care" class="rounded-full bg-hort-teal/20 px-2 py-0.5 text-[10px] font-semibold text-hort-teal-dark">
                                {{ $t('standby.care') }}
                            </span>
                        </div>

                        <div class="flex min-w-0 flex-1 flex-wrap gap-2">
                            <template v-for="slot in day.slots" :key="slot.slot">
                                <!-- Coordinator: a picker per slot -->
                                <label v-if="canManage" class="flex min-w-0 flex-1 items-center gap-2 sm:max-w-xs">
                                    <select
                                        :value="shownChildId(day, slot) ?? ''"
                                        class="w-full rounded-lg border-ink/20 text-sm focus:border-hort-teal focus:ring-hort-teal"
                                        :class="{
                                            'border-warn bg-warn/10': proposedKeys.has(slotKey(day.date, slot.slot)),
                                            'border-hort-teal bg-hort-teal/10': !proposedKeys.has(slotKey(day.date, slot.slot)) && slotKey(day.date, slot.slot) in draft,
                                            'text-warn-dark': shownChildId(day, slot) === null,
                                        }"
                                        :data-testid="`standby-slot-${day.date}-${slot.slot}`"
                                        @change="(e) => choose(day, slot, e.target.value)"
                                    >
                                        <option value="">{{ $t('standby.pick_child') }}</option>
                                        <option v-for="c in children" :key="c.id" :value="c.id">
                                            {{ c.name }} · {{ $t('standby.count_suffix', { count: countFor(c.id) }) }}
                                        </option>
                                    </select>
                                    <span
                                        v-if="proposedKeys.has(slotKey(day.date, slot.slot))"
                                        class="shrink-0 text-[10px] font-semibold uppercase text-warn-dark"
                                    >
                                        {{ $t('standby.proposed') }}
                                    </span>
                                    <span v-else-if="slot.former" class="shrink-0 text-xs text-ink/50">
                                        {{ $t('standby.former', { name: slot.former }) }}
                                    </span>
                                </label>

                                <!-- Everyone else: the name, own days highlighted -->
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm"
                                    :class="
                                        slot.own
                                            ? 'bg-hort-teal-dark font-semibold text-white'
                                            : slot.child
                                              ? 'bg-ink/5 text-ink'
                                              : 'bg-warn/10 text-warn-dark'
                                    "
                                    :data-testid="`standby-slot-${day.date}-${slot.slot}`"
                                >
                                    {{ slot.child?.name ?? $t('standby.open') }}
                                    <span v-if="slot.former" class="text-xs opacity-70">({{ $t('standby.former', { name: slot.former }) }})</span>
                                    <span v-if="slot.own" class="text-[10px] font-medium uppercase opacity-80">· {{ $t('standby.yours') }}</span>
                                </span>
                            </template>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Save bar: everything in the draft goes in one request. -->
        <div
            v-if="canManage && pendingCount"
            class="fixed inset-x-0 bottom-16 z-30 mx-auto flex max-w-3xl items-center justify-between gap-3 rounded-2xl bg-surface p-3 shadow-lg ring-1 ring-ink/10 sm:bottom-4"
            data-testid="standby-savebar"
        >
            <p class="text-sm font-medium text-ink">{{ $t('standby.pending', { count: pendingCount }) }}</p>
            <div class="flex gap-2">
                <SecondaryButton @click="discard">{{ $t('standby.discard') }}</SecondaryButton>
                <PrimaryButton :disabled="saving" data-testid="standby-save" @click="save">{{ $t('standby.save') }}</PrimaryButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
