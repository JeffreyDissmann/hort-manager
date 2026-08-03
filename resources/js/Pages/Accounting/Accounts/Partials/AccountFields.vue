<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DatePicker from '@/Components/DatePicker.vue';

// Shared account form fields, bound to a parent-provided useForm() instance.
defineProps({
    form: { type: Object, required: true },
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <InputLabel for="name" :value="$t('accounting.accounts.name')" />
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                :placeholder="$t('accounting.accounts.name_placeholder')"
                autofocus
            />
            <InputError :message="form.errors.name" class="mt-2" />
        </div>

        <div>
            <InputLabel for="iban" :value="$t('accounting.accounts.iban')" />
            <TextInput
                id="iban"
                v-model="form.iban"
                type="text"
                class="mt-1 block w-full font-mono"
                placeholder="DE00 0000 0000 0000 0000 00"
            />
            <InputError :message="form.errors.iban" class="mt-2" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <InputLabel for="opening_balance" :value="$t('accounting.accounts.opening_balance')" />
                <TextInput
                    id="opening_balance"
                    v-model="form.opening_balance"
                    type="number"
                    step="0.01"
                    class="mt-1 block w-full tabular-nums"
                    placeholder="0,00"
                />
                <InputError :message="form.errors.opening_balance" class="mt-2" />
            </div>

            <div>
                <InputLabel for="opening_balance_date" :value="$t('accounting.accounts.opening_balance_date')" />
                <DatePicker
                    id="opening_balance_date"
                    v-model="form.opening_balance_date"
                    clearable
                    class="mt-1"
                />
                <InputError :message="form.errors.opening_balance_date" class="mt-2" />
            </div>
        </div>

        <label class="flex items-start gap-3">
            <Checkbox :checked="form.active" @update:checked="form.active = $event" class="mt-1" />
            <span>
                <span class="text-sm font-medium text-ink">{{ $t('accounting.accounts.active') }}</span>
                <span class="block text-xs text-ink/50">{{ $t('accounting.accounts.active_hint') }}</span>
            </span>
        </label>
    </div>
</template>
