<script setup>
import { update as excursionsUpdate, index as excursionsIndex } from '@/routes/excursions';
import { update as pollsUpdate } from '@/routes/polls';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ExcursionFields from './Partials/ExcursionFields.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    excursion: { type: Object, required: true },
    children: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.excursion.name,
    date: props.excursion.date ?? '',
    depart_at: props.excursion.depart_at ?? '',
    return_at: props.excursion.return_at ?? '',
    rsvp_deadline: props.excursion.rsvp_deadline ?? '',
    note: props.excursion.note ?? '',
});

const canSubmit = computed(
    () => form.name && form.date && form.rsvp_deadline,
);

function submit() {
    form.put(excursionsUpdate(props.excursion.id).url);
}

function setResponse(childId, response) {
    router.patch(
        pollsUpdate(props.excursion.id).url,
        { child_id: childId, response },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="$t('excursions.edit_title', { name: excursion.name })" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">
                {{ $t('excursions.edit_heading') }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-2xl bg-white p-6 shadow-sm"
            >
                <ExcursionFields :form="form" />

                <div class="flex items-center justify-end gap-4">
                    <Link
                        :href="excursionsIndex().url"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >
                        {{ $t('common.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing || !canSubmit">
                        {{ $t('common.save') }}
                    </PrimaryButton>
                </div>
            </form>

            <!-- RSVP status — staff can also answer on a parent's behalf -->
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-hort-navy">{{ $t('excursions.responses_heading') }}</h3>
                <p class="mt-1 text-sm text-hort-navy/60">
                    {{ $t('excursions.responses_hint') }}
                </p>

                <ul class="mt-4 divide-y divide-gray-100">
                    <li
                        v-for="child in children"
                        :key="child.id"
                        class="flex items-center justify-between gap-3 py-2"
                    >
                        <div class="min-w-0">
                            <span class="font-medium text-hort-navy">
                                {{ child.name }}
                            </span>
                            <span
                                class="ml-2 text-xs font-semibold"
                                :class="{
                                    'text-hort-teal-dark': child.response === true,
                                    'text-hort-purple': child.response === false,
                                    'text-amber-600': child.response === null,
                                }"
                            >
                                {{
                                    child.response === true
                                        ? `✓ ${$t('excursions.joined')}`
                                        : child.response === false
                                          ? `✗ ${$t('excursions.not_joined')}`
                                          : $t('excursions.open')
                                }}
                            </span>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                                :class="child.response === true
                                    ? 'bg-hort-teal text-hort-navy'
                                    : 'bg-hort-navy/5 text-hort-navy/60 hover:bg-hort-teal/30'"
                                @click="setResponse(child.id, true)"
                            >
                                {{ $t('common.yes') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                                :class="child.response === false
                                    ? 'bg-hort-purple text-white'
                                    : 'bg-hort-navy/5 text-hort-navy/60 hover:bg-hort-purple/20'"
                                @click="setResponse(child.id, false)"
                            >
                                {{ $t('common.no') }}
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
