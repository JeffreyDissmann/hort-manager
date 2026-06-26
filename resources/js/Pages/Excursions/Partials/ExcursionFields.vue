<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

// The parent's useForm instance is passed in and bound directly.
defineProps({
    form: { type: Object, required: true },
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
            <InputLabel for="rsvp_deadline" value="Rückmeldung bis" />
            <TextInput
                id="rsvp_deadline"
                v-model="form.rsvp_deadline"
                type="date"
                class="mt-1 block w-full"
            />
            <p class="mt-1 text-sm text-gray-500">
                Bis zu diesem Tag können die Eltern abstimmen, ob ihr Kind
                mitkommt.
            </p>
            <InputError :message="form.errors.rsvp_deadline" class="mt-2" />
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

        <p class="rounded-xl bg-hort-teal/10 p-3 text-sm text-hort-navy/70">
            Nach dem Speichern werden <strong>alle Eltern</strong> gefragt, ob ihr
            Kind mitkommt.
        </p>
    </div>
</template>
