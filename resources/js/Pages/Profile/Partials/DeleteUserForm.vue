<script setup>
import { destroy as profileDestroy } from '@/routes/profile';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(profileDestroy().url, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Konto lÃ¶schen
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Wenn dein Konto gelÃ¶scht wird, werden alle zugehÃ¶rigen Daten dauerhaft entfernt. Bitte sichere vorher alle Daten, die du behalten mÃ¶chtest.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Konto lÃ¶schen</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900"
                >
                    MÃ¶chtest du dein Konto wirklich lÃ¶schen?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Wenn dein Konto gelÃ¶scht wird, werden alle zugehÃ¶rigen Daten dauerhaft entfernt. Bitte gib dein Passwort ein, um die LÃ¶schung zu bestÃ¤tigen.
                </p>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        value="Passwort"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="Passwort"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Abbrechen
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Konto lÃ¶schen
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
