<script setup>
// Springerdienst: the month list of which family stands by when Erzieher are ill.
// Everyone reads it (own days highlighted); the coordinator picks a child per slot.
// All edits — by hand or from the fair random proposal — collect in a draft and are
// saved together, so a proposal can be adjusted before anything is stored.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { index as standbyIndex, update as standbyUpdate } from '@/routes/standby';
import {
    store as swapStore,
    accept as swapAccept,
    decline as swapDecline,
    destroy as swapDestroy,
} from '@/routes/standby/swaps';
import { ArrowsRightLeftIcon, ChevronLeftIcon, ChevronRightIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    days: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    children: { type: Array, default: () => [] },
    counts: { type: [Object, Array], default: () => ({}) },
    proposal: { type: Array, default: null },
    // Swaps: the viewer's own dates from tomorrow on, and their open requests.
    ownUpcoming: { type: Array, default: () => [] },
    swaps: { type: Object, default: () => ({ incoming: [], outgoing: [] }) },
    today: { type: String, required: true },
});

const flash = computed(() => usePage().props.flash?.status);
const swapError = computed(() => usePage().props.errors?.swap);
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

// --- Swaps -------------------------------------------------------------------------
// A family can ask for any other family's date still ahead, offering one of its own.
const requestedDutyIds = computed(() => new Set(props.swaps.outgoing.map((s) => s.wanted.duty_id)));
const canAskFor = (day, slot) =>
    !props.canManage && slot.duty_id && !slot.own && day.date > props.today && props.ownUpcoming.length > 0;

const swapTarget = ref(null); // { day, slot }
const offeredDutyId = ref(null);

function openSwap(day, slot) {
    swapTarget.value = { day, slot };
    offeredDutyId.value = props.ownUpcoming[0]?.duty_id ?? null;
}

function sendSwap() {
    router.post(
        swapStore().url,
        { offered_duty_id: offeredDutyId.value, wanted_duty_id: swapTarget.value.slot.duty_id },
        { preserveScroll: true, onFinish: () => (swapTarget.value = null) },
    );
}

const answer = (url) => router.patch(url, {}, { preserveScroll: true });
const withdraw = (swap) => router.delete(swapDestroy(swap.id).url, { preserveScroll: true });

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

            <div v-if="swapError" class="rounded-2xl bg-danger/10 px-4 py-3 text-sm font-medium text-danger-dark" data-testid="standby-swap-error">
                {{ swapError }}
            </div>

            <p class="text-sm text-ink/60">{{ $t('standby.intro') }}</p>

            <!-- Requests from another family, waiting for this one's answer. -->
            <div v-if="swaps.incoming.length" class="space-y-2 rounded-2xl bg-warn/10 p-4 ring-1 ring-warn/40" data-testid="standby-swaps-incoming">
                <p class="text-sm font-semibold text-ink">{{ $t('standby.swaps_incoming') }}</p>
                <div v-for="swap in swaps.incoming" :key="swap.id" class="flex flex-wrap items-center gap-3">
                    <p class="min-w-0 flex-1 text-sm text-ink">
                        {{
                            $t('standby.swap_incoming_line', {
                                from_child: swap.offered.child,
                                to_child: swap.wanted.child,
                                offered_date: dayLabel(swap.offered.date),
                                wanted_date: dayLabel(swap.wanted.date),
                            })
                        }}
                    </p>
                    <div class="flex gap-2">
                        <SecondaryButton :data-testid="`swap-decline-${swap.id}`" @click="answer(swapDecline(swap.id).url)">
                            {{ $t('standby.swap_decline') }}
                        </SecondaryButton>
                        <PrimaryButton :data-testid="`swap-accept-${swap.id}`" @click="answer(swapAccept(swap.id).url)">
                            {{ $t('standby.swap_accept') }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- This family's own open requests. -->
            <div v-if="swaps.outgoing.length" class="space-y-2 rounded-2xl bg-surface p-4 shadow-sm" data-testid="standby-swaps-outgoing">
                <p class="text-sm font-semibold text-ink">{{ $t('standby.swaps_outgoing') }}</p>
                <div v-for="swap in swaps.outgoing" :key="swap.id" class="flex flex-wrap items-center gap-3">
                    <p class="min-w-0 flex-1 text-sm text-ink/70">
                        {{
                            $t('standby.swap_outgoing_line', {
                                from_child: swap.offered.child,
                                to_child: swap.wanted.child,
                                offered_date: dayLabel(swap.offered.date),
                                wanted_date: dayLabel(swap.wanted.date),
                            })
                        }}
                    </p>
                    <SecondaryButton :data-testid="`swap-cancel-${swap.id}`" @click="withdraw(swap)">
                        {{ $t('standby.swap_cancel') }}
                    </SecondaryButton>
                </div>
            </div>

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
                                <!-- Ask this family to swap (or show that it's already asked). -->
                                <span
                                    v-if="!canManage && slot.duty_id && requestedDutyIds.has(slot.duty_id)"
                                    class="self-center text-[10px] font-semibold uppercase text-warn-dark"
                                >
                                    {{ $t('standby.swap_pending') }}
                                </span>
                                <button
                                    v-else-if="canAskFor(day, slot)"
                                    type="button"
                                    class="inline-flex items-center gap-1 self-center rounded-full px-2 py-1 text-xs font-medium text-hort-teal-dark transition hover:bg-hort-teal/10"
                                    :data-testid="`standby-swap-${day.date}-${slot.slot}`"
                                    @click="openSwap(day, slot)"
                                >
                                    <ArrowsRightLeftIcon class="h-3.5 w-3.5" /> {{ $t('standby.swap') }}
                                </button>
                            </template>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Swap request: pick which own date to offer for the chosen one. -->
        <Modal :show="swapTarget !== null" max-width="md" :label="$t('standby.swap_title')" @close="swapTarget = null">
            <form v-if="swapTarget" class="space-y-4 p-6" @submit.prevent="sendSwap">
                <h3 class="text-lg font-semibold text-ink">{{ $t('standby.swap_title') }}</h3>
                <p class="text-sm text-ink/70">
                    {{ $t('standby.swap_intro', { child: swapTarget.slot.child.name, date: dayLabel(swapTarget.day.date) }) }}
                </p>
                <p v-if="!ownUpcoming.length" class="text-sm text-ink/60">{{ $t('standby.swap_none_to_offer') }}</p>
                <div class="space-y-1">
                    <label
                        v-for="own in ownUpcoming"
                        :key="own.duty_id"
                        class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm transition hover:bg-ink/5"
                    >
                        <input
                            v-model="offeredDutyId"
                            type="radio"
                            :value="own.duty_id"
                            class="border-ink/20 text-hort-teal-dark focus:ring-hort-teal"
                            :data-testid="`swap-offer-${own.duty_id}`"
                        />
                        <span class="tabular-nums text-ink">{{ dayLabel(own.date) }}</span>
                        <span class="text-ink/50">{{ own.child }}</span>
                    </label>
                </div>
                <p class="rounded-lg bg-ink/5 px-3 py-2 text-xs text-ink/70" data-testid="swap-confirm-note">
                    {{ $t('standby.swap_confirm_note', { child: swapTarget.slot.child.name }) }}
                </p>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="swapTarget = null">{{ $t('common.cancel') }}</SecondaryButton>
                    <PrimaryButton :disabled="!offeredDutyId" data-testid="swap-send">{{ $t('standby.swap_send') }}</PrimaryButton>
                </div>
            </form>
        </Modal>

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
