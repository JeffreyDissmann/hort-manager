<script setup>
// Ferien & Schließzeiten: the two Hort-wide period types. „Geschlossen" means no Hort
// at all; „Ferienbetreuung" offers days children opt into. Staff create both here and
// open a single one to edit it (its days, its roster); parents read the same list to
// plan around it.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PeriodFields from './Partials/PeriodFields.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { store as closuresStore, edit as closuresEdit, destroy as closuresDestroy } from '@/routes/closures';
import { program as programRoute } from '@/routes';
import { index as pollsIndex } from '@/routes/polls';
import { t } from '@/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    upcoming: { type: Array, default: () => [] },
    past: { type: Array, default: () => [] },
    // Ferienbetreuung periods, each with the days it offers.
    care: { type: Array, default: () => [] },
    careDefaults: { type: Object, default: () => ({ starts_at: '08:30', ends_at: '16:00' }) },
    canManage: { type: Boolean, default: false },
});

const flash = computed(() => usePage().props.flash?.status);
const locale = computed(() => usePage().props.locale || 'de');

const form = useForm({
    name: '',
    type: 'closed',
    starts_on: '',
    ends_on: '',
    registration_deadline: '',
    note: '',
});

const isCare = computed(() => form.type === 'care');
const showPast = ref(false);
const confirmingDelete = ref(null);

/** Creating only — an existing period is edited on its own page. */
function submit() {
    form
        .transform((data) => ({
            ...data,
            // A closure has nothing to register for.
            registration_deadline: data.type === 'care' ? data.registration_deadline || null : null,
        }))
        .post(closuresStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.clearErrors();
            },
        });
}

function remove(period) {
    router.delete(closuresDestroy(period.id).url, {
        preserveScroll: true,
        onFinish: () => (confirmingDelete.value = null),
    });
}

/** „31. Juli 2026" — a single date, for the registration deadline. */
function dateLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * The Betreuungszeit, which is what a parent planning around the week needs and
 * only staff could see until now (on the period's own page). Days may differ, so
 * say so rather than picking one of them to show.
 */
function timesLabel(period) {
    const windows = new Set(period.days.map((day) => `${day.starts_at}–${day.ends_at}`));

    return windows.size === 1 ? [...windows][0] : t('care.times_vary');
}

/** „12.–16. August 2026", or a single day when the period is one day long. */
function rangeLabel(period) {
    const from = new Date(`${period.starts_on}T00:00:00`);
    const to = new Date(`${period.ends_on}T00:00:00`);
    const long = { day: 'numeric', month: 'long', year: 'numeric' };

    if (period.starts_on === period.ends_on) {
        return from.toLocaleDateString(locale.value, { weekday: 'long', ...long });
    }

    return `${from.toLocaleDateString(locale.value, { day: 'numeric', month: 'long' })} – ${to.toLocaleDateString(locale.value, long)}`;
}
</script>

<template>
    <Head :title="$t('closures.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('closures.header') }}</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <p class="text-sm text-ink/60">{{ $t('closures.intro') }}</p>

            <!-- Add (staff only). Editing happens on the period's own page. -->
            <div v-if="canManage" class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="mb-3 font-semibold text-ink">{{ $t('closures.add_heading') }}</p>

                <PeriodFields :form="form" show-type-toggle />

                <p v-if="isCare" class="mt-2 text-xs text-ink/50">
                    {{ $t('care.generates_days', { start: careDefaults.starts_at, end: careDefaults.ends_at }) }}
                </p>

                <div class="mt-3 flex justify-end">
                    <PrimaryButton
                        data-testid="closure-save"
                        :disabled="form.processing || !form.name || !form.starts_on || !form.ends_on"
                        @click="submit"
                    >
                        {{ $t('closures.add') }}
                    </PrimaryButton>
                </div>
            </div>

            <!-- Ferienbetreuung -->
            <div v-if="care.length" class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="mb-3 font-semibold text-ink">{{ $t('care.heading') }}</p>

                <div
                    v-for="period in care"
                    :key="period.id"
                    :data-testid="`care-period-${period.id}`"
                    class="flex flex-wrap items-start justify-between gap-3 border-b border-ink/5 py-3 first:pt-0 last:border-0 last:pb-0"
                >
                    <div>
                        <p class="font-medium text-ink">{{ period.name }}</p>
                        <p class="text-sm text-ink/60">
                            {{ rangeLabel(period) }}
                            <span class="text-ink/40">
                                ·
                                {{
                                    period.day_count === 1
                                        ? $t('closures.day_one')
                                        : $t('closures.day_many', { count: period.day_count })
                                }}
                            </span>
                            <span v-if="period.day_count" class="text-ink/40">
                                · {{ timesLabel(period) }}
                            </span>
                        </p>
                        <p
                            class="mt-0.5 text-xs"
                            :class="period.registration_open ? 'text-ink/50' : 'text-hort-orange-dark'"
                        >
                            <template v-if="period.registration_deadline">
                                {{
                                    period.registration_open
                                        ? $t('care.deadline_open', { date: dateLabel(period.registration_deadline) })
                                        : $t('care.deadline_passed', { date: dateLabel(period.registration_deadline) })
                                }}
                            </template>
                            <template v-else>{{ $t('care.no_deadline') }}</template>
                        </p>
                        <p v-if="period.note" class="mt-0.5 text-xs text-ink/50">{{ period.note }}</p>
                        <!-- Essen + Aktivität live on /program; jump straight to that
                             week rather than making staff click through to it. -->
                        <Link
                            v-if="canManage"
                            :href="programRoute({ query: { week: period.starts_on } }).url"
                            :data-testid="`care-program-${period.id}`"
                            class="mt-1 inline-block text-xs font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                        >
                            {{ $t('care.plan_program') }}
                        </Link>
                        <!-- Parents read this page to plan around the period; without
                             this they had to know that signing up happens elsewhere. -->
                        <Link
                            v-if="!canManage && period.registration_open"
                            :href="pollsIndex().url"
                            :data-testid="`care-signup-${period.id}`"
                            class="mt-1 inline-block text-xs font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                        >
                            {{ $t('weekly.care_signup_link') }} →
                        </Link>
                    </div>

                    <div v-if="canManage" class="flex shrink-0 items-center gap-2">
                        <template v-if="confirmingDelete === period.id">
                            <span class="text-sm text-ink/60">{{ $t('closures.delete_confirm') }}</span>
                            <DangerButton @click="remove(period)">{{ $t('common.delete') }}</DangerButton>
                            <SecondaryButton @click="confirmingDelete = null">
                                {{ $t('common.cancel') }}
                            </SecondaryButton>
                        </template>
                        <template v-else>
                            <Link :href="closuresEdit(period.id).url" :data-testid="`care-edit-${period.id}`">
                                <SecondaryButton>{{ $t('closures.open') }}</SecondaryButton>
                            </Link>
                            <SecondaryButton @click="confirmingDelete = period.id">
                                {{ $t('common.delete') }}
                            </SecondaryButton>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Upcoming Schließzeiten -->
            <div class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="mb-3 font-semibold text-ink">{{ $t('closures.upcoming_heading') }}</p>

                <p v-if="!upcoming.length" class="text-sm text-ink/50" data-testid="closures-empty">
                    {{ $t('closures.none') }}
                </p>

                <ul v-else class="divide-y divide-ink/5">
                    <li
                        v-for="period in upcoming"
                        :key="period.id"
                        :data-testid="`closure-${period.id}`"
                        class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div>
                            <p class="font-medium text-ink">{{ period.name }}</p>
                            <p class="text-sm text-ink/60">
                                {{ rangeLabel(period) }}
                                <span class="text-ink/40">
                                    ·
                                    {{
                                        period.days === 1
                                            ? $t('closures.day_one')
                                            : $t('closures.day_many', { count: period.days })
                                    }}
                                </span>
                            </p>
                            <p v-if="period.note" class="mt-0.5 text-xs text-ink/50">{{ period.note }}</p>
                        </div>

                        <div v-if="canManage" class="flex shrink-0 items-center gap-2">
                            <template v-if="confirmingDelete === period.id">
                                <span class="text-sm text-ink/60">{{ $t('closures.delete_confirm') }}</span>
                                <DangerButton
                                    :data-testid="`closure-delete-confirm-${period.id}`"
                                    @click="remove(period)"
                                >
                                    {{ $t('common.delete') }}
                                </DangerButton>
                                <SecondaryButton @click="confirmingDelete = null">
                                    {{ $t('common.cancel') }}
                                </SecondaryButton>
                            </template>
                            <template v-else>
                                <Link :href="closuresEdit(period.id).url" :data-testid="`closure-edit-${period.id}`">
                                    <SecondaryButton>{{ $t('closures.open') }}</SecondaryButton>
                                </Link>
                                <SecondaryButton
                                    :data-testid="`closure-delete-${period.id}`"
                                    @click="confirmingDelete = period.id"
                                >
                                    {{ $t('common.delete') }}
                                </SecondaryButton>
                            </template>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Past, collapsed -->
            <div v-if="past.length" class="rounded-2xl bg-surface p-4 shadow-sm">
                <button
                    type="button"
                    data-testid="closures-past-toggle"
                    class="text-sm font-medium text-ink/60 underline-offset-2 hover:underline"
                    @click="showPast = !showPast"
                >
                    {{ showPast ? $t('closures.hide_past') : $t('closures.show_past', { count: past.length }) }}
                </button>

                <ul v-if="showPast" class="mt-3 divide-y divide-ink/5">
                    <li v-for="period in past" :key="period.id" class="py-2 first:pt-0 last:pb-0">
                        <p class="text-sm text-ink/70">{{ period.name }}</p>
                        <p class="text-xs text-ink/50">{{ rangeLabel(period) }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
