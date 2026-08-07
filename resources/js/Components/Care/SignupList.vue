<script setup>
// Ferienbetreuung sign-up: per child, tick the days they'll come. Saving sends the
// full set of days for that child, so unticking is just as much an answer as ticking.
// Lives in a component because two pages show it: „Ausflüge & Ferien" for parents and
// the staff-only /care (every child, and after the Anmeldeschluss).
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { update as careUpdate } from '@/routes/care';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    children: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    canOverrideDeadline: { type: Boolean, default: false },
    // The period's own page already says which period this is (and when it runs).
    showPeriodHeader: { type: Boolean, default: true },
});

const locale = computed(() => usePage().props.locale || 'de');

// Local ticks: { 'periodId|childId': [dayId], seeded from what's saved.
const picks = reactive({});
const saving = ref(null);

// Children whose answer is in but whose grid the user opened again, by `key()`.
const reopened = reactive({});

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
    // Days already under way aren't ours to change — keep whatever is saved for them.
    const fixed = selection(period, child).filter(
        (id) => !period.days.find((d) => d.id === id && dayEditable(period, d)),
    );
    const open = period.days.filter((d) => dayEditable(period, d)).map((d) => d.id);

    picks[key(period.id, child.id)] = all ? [...fixed, ...open] : fixed;
}

/** The children enrolled while this period runs — enrolment differs per period. */
function childrenFor(period) {
    return props.children.filter((child) => period.child_ids.includes(child.id));
}

/** Whether this user may still change this period's sign-ups. */
function editable(period) {
    return period.open || props.canOverrideDeadline;
}

const today = new Date().toLocaleDateString('sv'); // ISO-ish: YYYY-MM-DD

/**
 * A day that has started belongs to the Tagesboard — staff mark children off there,
 * and the server refuses to withdraw someone who may already be in the Hort. Showing
 * the box as editable would promise something saving doesn't do.
 */
function dayEditable(period, day) {
    return editable(period) && day.date > today;
}

function save(period, child) {
    const k = key(period.id, child.id);
    saving.value = k;
    router.patch(
        careUpdate(period.id).url,
        { child_id: child.id, day_ids: selection(period, child) },
        {
            preserveScroll: true,
            // Drop the local ticks so this child's boxes re-seed from what the server
            // actually stored: a day it refused (already over, or closed since the page
            // loaded) would otherwise stay ticked and look registered. Only this child's
            // entry, so a sibling's unsaved ticks survive.
            // …and fold the grid back up: the summary it leaves behind is the
            // confirmation that the answer landed.
            onSuccess: () => {
                delete picks[k];
                delete reopened[k];
            },
            onFinish: () => (saving.value = null),
        },
    );
}

/** The days this child is signed up for — the whole answer, once it can't change. */
function registeredDays(period, child) {
    return period.days.filter((day) => day.children.includes(child.id));
}

function hasAnswered(period, child) {
    return period.answered.includes(child.id);
}

/**
 * The days are only worth a grid while they're a question. An answered child (or a
 * period past its deadline) shows the answer instead, until „Ändern" asks again —
 * a family with three children was scrolling three full grids for nothing.
 */
function asksForAnAnswer(period, child) {
    return editable(period) && (! hasAnswered(period, child) || reopened[key(period.id, child.id)]);
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
    <div class="space-y-4">
        <div
            v-for="period in periods"
            :key="period.id"
            :data-testid="`care-signup-${period.id}`"
            class="rounded-2xl bg-surface p-4 shadow-sm"
        >
            <p v-if="showPeriodHeader" class="font-semibold text-ink">{{ period.name }}</p>
            <p v-if="showPeriodHeader" class="text-sm text-ink/60">
                {{ dateLabel(period.starts_on) }} – {{ dateLabel(period.ends_on) }}
            </p>
            <p class="mt-0.5 text-xs" :class="period.open ? 'text-ink/50' : 'text-hort-orange-dark'">
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
            <p v-if="period.note && showPeriodHeader" class="mt-1 text-sm text-ink/60">{{ period.note }}</p>

            <div
                v-for="child in childrenFor(period)"
                :key="child.id"
                class="mt-4 border-t border-ink/5 pt-3 first:border-0"
            >
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium text-ink">
                        {{ child.name }}
                        <!-- „nicht beantwortet" is a nudge; once the deadline has
                             passed there is nothing left to nudge towards. -->
                        <span
                            v-if="!hasAnswered(period, child) && editable(period)"
                            class="ml-1 text-xs font-normal text-hort-orange-dark"
                        >
                            · {{ $t('care.not_answered') }}
                        </span>
                    </p>

                    <div v-if="asksForAnAnswer(period, child)" class="flex gap-2">
                        <SecondaryButton @click="pickAll(period, child, true)">
                            {{ $t('care.all_days') }}
                        </SecondaryButton>
                        <SecondaryButton @click="pickAll(period, child, false)">
                            {{ $t('care.no_days') }}
                        </SecondaryButton>
                    </div>

                    <SecondaryButton
                        v-else-if="editable(period)"
                        :data-testid="`care-change-${period.id}-${child.id}`"
                        @click="reopened[`${period.id}|${child.id}`] = true"
                    >
                        {{ $t('common.edit') }}
                    </SecondaryButton>
                </div>

                <!-- The answer, not the question: either it's already given, or the
                     deadline has passed and the grid would be dead checkboxes. -->
                <p v-if="!asksForAnAnswer(period, child)" class="text-sm text-ink/70">
                    <template v-if="registeredDays(period, child).length">
                        {{
                            $t('care.registered_for', {
                                days: registeredDays(period, child).map((d) => dayLabel(d.date)).join(', '),
                            })
                        }}
                    </template>
                    <template v-else>{{ $t('care.registered_none') }}</template>
                </p>

                <ul v-else class="space-y-1">
                    <li
                        v-for="day in period.days"
                        :key="day.id"
                        class="flex flex-wrap items-center gap-2 text-sm"
                    >
                        <label
                            class="flex flex-1 items-center gap-2"
                            :class="dayEditable(period, day) ? 'cursor-pointer' : ''"
                        >
                            <Checkbox
                                :checked="isPicked(period, child, day)"
                                :disabled="!dayEditable(period, day)"
                                :data-testid="`care-pick-${child.id}-${day.id}`"
                                @update:checked="(v) => toggle(period, child, day, v)"
                            />
                            <span
                                class="w-24 shrink-0 font-medium"
                                :class="day.date > today ? 'text-ink' : 'text-ink/40'"
                            >
                                {{ dayLabel(day.date) }}
                            </span>
                            <span :class="day.date > today ? 'text-ink/70' : 'text-ink/40'">
                                {{ day.starts_at }}–{{ day.ends_at }}
                            </span>
                        </label>
                    </li>
                </ul>

                <div v-if="asksForAnAnswer(period, child)" class="mt-2 flex justify-end">
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
</template>
