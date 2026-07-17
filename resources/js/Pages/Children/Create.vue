<script setup>
import { store as childrenStore, index as childrenIndex } from '@/routes/children';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { t } from '@/i18n';

const form = useForm({
    name: '',
    date_of_birth: '',
    note: '',
});

function submit() {
    form.post(childrenStore().url);
}
</script>

<template>
    <Head :title="$t('children.add_child')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-ink">
                {{ $t('children.add_child') }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-2xl bg-surface p-6 shadow-sm"
            >
                    <div>
                        <InputLabel for="name" :value="$t('children.name')" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            autofocus
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="date_of_birth" :value="$t('children.date_of_birth')" />
                        <DatePicker
                            id="date_of_birth"
                            v-model="form.date_of_birth"
                            clearable
                            class="mt-1"
                        />
                        <InputError
                            :message="form.errors.date_of_birth"
                            class="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel for="note" :value="$t('children.note_label')" />
                        <textarea
                            id="note"
                            v-model="form.note"
                            rows="3"
                            :placeholder="$t('children.note_placeholder')"
                            class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                        ></textarea>
                        <InputError :message="form.errors.note" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="childrenIndex().url"
                            class="text-sm text-ink/70 hover:text-ink"
                        >
                            {{ $t('common.cancel') }}
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            {{ $t('common.save') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
    </AuthenticatedLayout>
</template>
