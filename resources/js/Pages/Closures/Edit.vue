<script setup>
// One Ferien-Zeitraum on its own page — its fields, the days it offers and who is
// signed up, the way an Ausflug carries its fields and its answers. Staff only.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CareDayRow from '@/Components/CareDayRow.vue';
import CareSignupList from '@/Components/Care/SignupList.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PeriodFields from './Partials/PeriodFields.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { index as closuresIndex, update as closuresUpdate, destroy as closuresDestroy } from '@/routes/closures';
import { restore as careDayRestore } from '@/routes/care-days';
import { program as programRoute } from '@/routes';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    period: { type: Object, required: true },
    // Every child × every offered day — null for a Schließzeit, which has no roster.
    roster: { type: Object, default: null },
});

const flash = computed(() => usePage().props.flash?.status);
const locale = computed(() => usePage().props.locale || 'de');
const confirmingDelete = ref(false);

const form = useForm({
    name: props.period.name,
    type: props.period.type,
    starts_on: props.period.starts_on,
    ends_on: props.period.ends_on,
    registration_deadline: props.period.registration_deadline ?? '',
    note: props.period.note ?? '',
});

const isCare = computed(() => props.period.type === 'care');

function save() {
    form
        .transform((data) => ({
            ...data,
            // A closure has nothing to register for.
            registration_deadline: data.type === 'care' ? data.registration_deadline || null : null,
        }))
        .patch(closuresUpdate(props.period.id).url, { preserveScroll: true });
}

function remove() {
    router.delete(closuresDestroy(props.period.id).url);
}

/** Put a removed day back on the sign-up sheet (its sign-ups are not restored). */
function restoreDay(day) {
    router.patch(careDayRestore(day.id).url, {}, { preserveScroll: true });
}

function dayLabel(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

// How many children are signed up per offered day — the question staff actually
// have when they plan the food and the staffing.
const signedUpPerDay = computed(() =>
    Object.fromEntries(
        (props.roster?.periods?.[0]?.days ?? []).map((day) => [day.id, day.children.length]),
    ),
);
</script>

<template>
    <Head :title="period.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-xl font-semibold text-ink">{{ period.name }}</h2>
                <Link
                    :href="closuresIndex().url"
                    class="text-sm font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                >
                    ← {{ $t('closures.back_to_list') }}
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-4">
            <div v-if="flash" class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink">
                {{ flash }}
            </div>

            <!-- The period itself -->
            <div class="rounded-2xl bg-surface p-4 shadow-sm">
                <PeriodFields :form="form" />

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <template v-if="confirmingDelete">
                        <span class="text-sm text-ink/60">{{ $t('closures.delete_confirm') }}</span>
                        <div class="flex gap-2">
                            <DangerButton data-testid="period-delete-confirm" @click="remove">
                                {{ $t('common.delete') }}
                            </DangerButton>
                            <SecondaryButton @click="confirmingDelete = false">
                                {{ $t('common.cancel') }}
                            </SecondaryButton>
                        </div>
                    </template>
                    <template v-else>
                        <SecondaryButton data-testid="period-delete" @click="confirmingDelete = true">
                            {{ $t('common.delete') }}
                        </SecondaryButton>
                        <PrimaryButton
                            data-testid="period-save"
                            :disabled="form.processing || !form.name || !form.starts_on || !form.ends_on"
                            @click="save"
                        >
                            {{ $t('common.save') }}
                        </PrimaryButton>
                    </template>
                </div>
            </div>

            <!-- The days it offers (Ferienbetreuung only) -->
            <div v-if="isCare" class="rounded-2xl bg-surface p-4 shadow-sm">
                <p class="font-semibold text-ink">{{ $t('care.days_heading') }}</p>
                <p class="mt-1 text-sm text-ink/60">{{ $t('care.days_intro') }}</p>
                <!-- Essen + Aktivität live on /program; open that week directly rather
                     than making staff find it. -->
                <Link
                    :href="programRoute({ query: { week: period.starts_on } }).url"
                    data-testid="care-program-link"
                    class="mb-2 inline-block text-sm font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                >
                    {{ $t('care.plan_program') }} →
                </Link>

                <ul class="divide-y divide-ink/5">
                    <CareDayRow
                        v-for="day in period.days"
                        :key="day.id"
                        :day="day"
                        :can-manage="true"
                        :signed-up="signedUpPerDay[day.id] ?? 0"
                    />
                </ul>

                <div v-if="period.removed_days.length" class="mt-3 border-t border-ink/5 pt-2">
                    <p class="text-xs font-medium text-ink/50">{{ $t('care.removed_heading') }}</p>
                    <div
                        v-for="day in period.removed_days"
                        :key="day.id"
                        :data-testid="`care-day-removed-${day.id}`"
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 py-1 text-sm"
                    >
                        <span class="w-24 shrink-0 text-ink/50">{{ dayLabel(day.date) }}</span>
                        <SecondaryButton :data-testid="`care-day-restore-${day.id}`" @click="restoreDay(day)">
                            {{ $t('care.restore_day') }}
                        </SecondaryButton>
                    </div>
                </div>
            </div>

            <!-- Who is coming — staff may tick for any child, deadline or not -->
            <div v-if="isCare && roster" class="space-y-2">
                <div class="px-1">
                    <h3 class="font-semibold text-ink">{{ $t('care.roster_heading') }}</h3>
                    <p class="text-sm text-ink/60">{{ $t('care.roster_intro') }}</p>
                </div>

                <CareSignupList
                    :children="roster.children"
                    :periods="roster.periods"
                    :can-override-deadline="roster.canOverrideDeadline"
                    :show-period-header="false"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
