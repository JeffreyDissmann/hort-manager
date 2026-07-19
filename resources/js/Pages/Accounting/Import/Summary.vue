<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { review as bookingsReview, index as bookingsIndex } from '@/routes/accounting/bookings';
import { CheckCircleIcon } from '@heroicons/vue/24/outline';

defineProps({
    batch: { type: Object, required: true },
    draftTotal: { type: Number, required: true },
});
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
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="bookingsIndex().url" class="self-center text-sm text-ink/70 hover:text-ink">
                    {{ $t('accounting.import.view_bookings') }}
                </Link>
                <Link v-if="draftTotal > 0" :href="bookingsReview().url">
                    <PrimaryButton>{{ $t('accounting.import.start_review') }}</PrimaryButton>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
