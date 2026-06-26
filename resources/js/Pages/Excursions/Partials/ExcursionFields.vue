<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

// The parent's useForm instance is passed in and bound directly.
defineProps({
    form: { type: Object, required: true },
    allChildren: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <InputLabel for="name" value="Name des Ausflugs" />
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                placeholder="z. B. Zoo-Ausflug"
                autofocus
            />
            <InputError :message="form.errors.name" class="mt-2" />
        </div>

        <div>
            <InputLabel for="date" value="Datum" />
            <TextInput
                id="date"
                v-model="form.date"
                type="date"
                class="mt-1 block w-full"
            />
            <InputError :message="form.errors.date" class="mt-2" />
        </div>

        <div class="flex gap-3">
            <div class="flex-1">
                <InputLabel for="depart_at" value="Abfahrt" />
                <TextInput
                    id="depart_at"
                    v-model="form.depart_at"
                    type="time"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.depart_at" class="mt-2" />
            </div>
            <div class="flex-1">
                <InputLabel for="return_at" value="Rückkehr" />
                <TextInput
                    id="return_at"
                    v-model="form.return_at"
                    type="time"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.return_at" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel for="note" value="Notiz (optional)" />
            <textarea
                id="note"
                v-model="form.note"
                rows="2"
                placeholder="z. B. Brotzeit und feste Schuhe mitbringen"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
            ></textarea>
            <InputError :message="form.errors.note" class="mt-2" />
        </div>

        <div>
            <InputLabel value="Wer kommt mit?" />
            <p class="mb-2 mt-1 text-sm text-gray-500">
                Diese Kinder erscheinen am Ausflugstag im Tagesboard als
                „unterwegs“.
            </p>
            <div
                v-if="allChildren.length"
                class="grid grid-cols-2 gap-1 rounded-md border border-gray-200 p-2 sm:grid-cols-3"
            >
                <label
                    v-for="child in allChildren"
                    :key="child.id"
                    class="flex cursor-pointer items-center gap-2 rounded-lg p-2 hover:bg-hort-sand"
                >
                    <input
                        type="checkbox"
                        :value="child.id"
                        v-model="form.children"
                        class="rounded border-gray-300 text-hort-teal-dark focus:ring-hort-teal"
                    />
                    <span class="truncate text-sm font-medium text-hort-navy">
                        {{ child.name }}
                    </span>
                </label>
            </div>
            <p v-else class="text-sm text-gray-500">
                Noch keine Kinder angelegt.
            </p>
        </div>
    </div>
</template>
