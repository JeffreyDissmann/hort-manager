<script setup>
import { computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { review as bookingsReview, index as bookingsIndex } from '@/routes/accounting/bookings';
import { CheckCircleIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    batch: { type: Object, required: true },
    draftTotal: { type: Number, required: true },
    progress: { type: Object, required: true }, // { total, analyzed, pending }
});

// Poll the AI progress until every draft has been analysed.
const { stop } = usePoll(2500, { only: ['progress', 'draftTotal'] });
watch(
    () => props.progress.pending,
    (pending) => {
        if (pending === 0) {
            stop();
        }
    },
    { immediate: true },
);

const analysing = computed(() => props.progress.pending > 0);
// Only allow review once at least one booking has an AI suggestion.
const canReview = computed(() => props.progress.analyzed > 0);
const percent = computed(() =>
    props.progress.total > 0 ? Math.round((props.progress.analyzed / props.progress.total) * 100) : 0,
);
</script>

<template>
    <Head :title="$t('accounting.import.summary_title')" />

    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ $t('accounting.title') }}</p>
            <h2 class="text-xl font-semibold text-ink">{{ $t('accounting.import.summary_title') }}</h2>
        </template>

        <div class="mx-auto max-w-lg space-y-5">
            <div class="rounded-2xl bg-surface p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-3">
                    <CheckCircleIcon class="h-8 w-8 text-hort-teal-dark" />
                    <div>
                        <p class="font-medium text-ink">{{ batch.filename }}</p>
                        <p class="text-sm text-ink/50">{{ batch.account }}</p>
                    </div>
                </div>

                <dl class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-hort-teal/10 p-3">
                        <dt class="text-xs text-ink/50">{{ $t('accounting.import.imported_label') }}</dt>
                        <dd class="text-2xl font-semibold text-hort-teal-dark">{{ batch.imported_count }}</dd>
                    </div>
                    <div class="rounded-xl bg-ink/5 p-3">
                        <dt class="text-xs text-ink/50">{{ $t('accounting.import.duplicates_label') }}</dt>
                        <dd class="text-2xl font-semibold text-ink/70">{{ batch.duplicate_count }}</dd>
                    </div>
                    <div class="rounded-xl bg-amber-100 p-3">
                        <dt class="text-xs text-ink/50">{{ $t('accounting.import.to_review') }}</dt>
                        <dd class="text-2xl font-semibold text-amber-700">{{ draftTotal }}</dd>
                    </div>
                </dl>

                <!-- AI analysis progress -->
                <div class="mt-5 border-t border-ink/10 pt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-1.5 font-medium text-ink/70">
                            <ArrowPathIcon v-if="analysing" class="h-4 w-4 animate-spin text-hort-teal-dark" />
                            <CheckCircleIcon v-else class="h-4 w-4 text-hort-teal-dark" />
                            {{ analysing ? $t('accounting.import.analysing') : $t('accounting.import.analysed_done') }}
                        </span>
                        <span class="tabular-nums text-ink/50">{{ progress.analyzed }} / {{ progress.total }}</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink/10">
                        <div class="h-full rounded-full bg-hort-teal-dark transition-all duration-500" :style="{ width: percent + '%' }" />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <Link :href="bookingsIndex().url" class="self-center text-sm text-ink/70 hover:text-ink">
                    {{ $t('accounting.import.view_bookings') }}
                </Link>
                <Link v-if="canReview" :href="bookingsReview().url">
                    <PrimaryButton>{{ $t('accounting.import.start_review') }}</PrimaryButton>
                </Link>
                <PrimaryButton v-else disabled :title="$t('accounting.import.waiting_ai')">
                    {{ $t('accounting.import.start_review') }}
                </PrimaryButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
