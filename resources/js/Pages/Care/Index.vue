<script setup>
// Ferienbetreuung sign-up: per child, tick the days they'll come. Parents see their
// own children, staff see everyone. Saving sends the full set of days for that child,
// so unticking is just as much an answer as ticking.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { update as careUpdate } from '@/routes/care';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    children: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    canOverrideDeadline: { type: Boolean, default: false },
});

const flash = computed(() => usePage().props.flash?.status);
const locale = computed(() => usePage().props.locale || 'de');

// Local ticks: { 'periodId|childId': Set(dayId) }, seeded from what's saved.
const picks = reactive({});
const saving = ref(null);

function key(periodId, childId) {
    return `${periodId}|${childId}`;
}

function selection(period, child) {
    const k = key(period.id, child.id);
    if (!picks[k]) {
        picks[k] = period.days.filter((d) => d.children.includes(child.id)).map((d) => d.id);
    }
    return picks[k];
}

function isPicked(period, child, day) {
    return selection(period, child).includes(day.id);
}

function toggle(period, child, day, checked) {
    const k = key(period.id, child.id);
    const current = selection(period, child);
    picks[k] = checked ? [...current, day.id] : current.filter((id) => id !== day.id);
}

function pickAll(period, child, all) {
    picks[key(period.id, child.id)] = all ? period.days.map((d) => d.id) : [];
}

/** The children enrolled while this period runs — enrolment differs per period. */
function childrenFor(period) {
    return props.children.filter((child) => period.child_ids.includes(child.id));
}

/** Whether this user may still change this period's sign-ups. */
function editable(period) {
    return period.open || props.canOverrideDeadline;
}

function save(period, child) {
    const k = key(period.id, child.id);
    saving.value = k;
    router.patch(
        careUpdate(period.id).url,
        { child_id: child.id, day_ids: selection(period, child) },
        { preserveScroll: true, onFinish: () => (saving.value = null) },
    );
}

function hasAnswered(period, child) {
    return period.answered.includes(child.id);
}

function dayLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

function dateLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="$t('care.heading')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('care.heading') }}</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <p v-if="!periods.length" class="rounded-2xl bg-surface p-6 text-center text-sm text-ink/50">
                {{ $t('care.none') }}
            </p>

            <div
                v-for="period in periods"
                :key="period.id"
                :data-testid="`care-signup-${period.id}`"
                class="rounded-2xl bg-surface p-4 shadow-sm"
            >
                <p class="font-semibold text-ink">{{ period.name }}</p>
                <p class="text-sm text-ink/60">
                    {{ dateLabel(period.starts_on) }} – {{ dateLabel(period.ends_on) }}
                </p>
                <p
                    class="mt-0.5 text-xs"
                    :class="period.open ? 'text-ink/50' : 'text-hort-orange-dark'"
                >
                    <template v-if="period.registration_deadline">
                        {{
                            period.open
                                ? $t('care.deadline_open', { date: dateLabel(period.registration_deadline) })
                                : $t('care.deadline_passed', { date: dateLabel(period.registration_deadline) })
                        }}
                    </template>
                    <template v-else>{{ $t('care.no_deadline') }}</template>
                    <span v-if="!period.open && canOverrideDeadline"> · {{ $t('care.staff_may_still_edit') }}</span>
                </p>
                <p v-if="period.note" class="mt-1 text-sm text-ink/60">{{ period.note }}</p>

                <div
                    v-for="child in childrenFor(period)"
                    :key="child.id"
                    class="mt-4 border-t border-ink/5 pt-3 first:border-0"
                >
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium text-ink">
                            {{ child.name }}
                            <span
                                v-if="!hasAnswered(period, child)"
                                class="ml-1 text-xs font-normal text-hort-orange-dark"
                            >
                                · {{ $t('care.not_answered') }}
                            </span>
                        </p>

                        <div v-if="editable(period)" class="flex gap-2">
                            <SecondaryButton @click="pickAll(period, child, true)">
                                {{ $t('care.all_days') }}
                            </SecondaryButton>
                            <SecondaryButton @click="pickAll(period, child, false)">
                                {{ $t('care.no_days') }}
                            </SecondaryButton>
                        </div>
                    </div>

                    <ul class="space-y-1">
                        <li
                            v-for="day in period.days"
                            :key="day.id"
                            class="flex flex-wrap items-center gap-2 text-sm"
                        >
                            <label class="flex flex-1 items-center gap-2" :class="editable(period) ? 'cursor-pointer' : ''">
                                <Checkbox
                                    :checked="isPicked(period, child, day)"
                                    :disabled="!editable(period)"
                                    :data-testid="`care-pick-${child.id}-${day.id}`"
                                    @update:checked="(v) => toggle(period, child, day, v)"
                                />
                                <span class="w-24 shrink-0 font-medium text-ink">{{ dayLabel(day.date) }}</span>
                                <span class="text-ink/70">{{ day.starts_at }}–{{ day.ends_at }}</span>
                            </label>
                        </li>
                    </ul>

                    <div v-if="editable(period)" class="mt-2 flex justify-end">
                        <PrimaryButton
                            :data-testid="`care-save-${period.id}-${child.id}`"
                            :disabled="saving === `${period.id}|${child.id}`"
                            @click="save(period, child)"
                        >
                            {{ $t('common.save') }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
