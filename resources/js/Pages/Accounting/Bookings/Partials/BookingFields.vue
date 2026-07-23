<script setup>
import { ref, computed, watch } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import DatePicker from '@/Components/DatePicker.vue';
import CategorySelect from '@/Components/Accounting/CategorySelect.vue';
import { activeInYear, yearOf } from '@/childActivity';

const props = defineProps({
    form: { type: Object, required: true },
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    children: { type: Array, default: () => [] },
    users: { type: Array, required: true },
    // Optional: restrict the category picker to one direction (used during review).
    direction: { type: String, default: null },
});

// Counterparty is a child (income), a linked user (person), free text, or nothing.
const mode = ref(
    props.form.counterparty_child_id
        ? 'child'
        : props.form.counterparty_user_id
          ? 'user'
          : props.form.counterparty_name
            ? 'free'
            : 'none',
);

watch(mode, (value) => {
    if (value !== 'child') {
        props.form.counterparty_child_id = null;
    }
    if (value !== 'user') {
        props.form.counterparty_user_id = null;
    }
    if (value !== 'free') {
        props.form.counterparty_name = '';
    }
});

const modes = [
    { value: 'none', key: 'counterparty_none' },
    { value: 'child', key: 'counterparty_child' },
    { value: 'user', key: 'counterparty_user' },
    { value: 'free', key: 'counterparty_free' },
];

// Only children enrolled in the booking's year can be picked; the list updates live
// as the date changes. Keep the already-selected child visible even if inactive then.
const availableChildren = computed(() => {
    const year = yearOf(props.form.booking_date);
    const list = props.children.filter((c) => activeInYear(c, year));
    const selected = props.form.counterparty_child_id;
    if (selected && !list.some((c) => c.id === selected)) {
        const current = props.children.find((c) => c.id === selected);
        if (current) {
            list.unshift(current);
        }
    }
    return list;
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <InputLabel for="account_id" :value="$t('accounting.bookings.account')" />
                <select
                    id="account_id"
                    v-model="form.account_id"
                    class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
                >
                    <option :value="null">{{ $t('accounting.bookings.pick_account') }}</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                </select>
                <InputError :message="form.errors.account_id" class="mt-2" />
            </div>

            <div>
                <InputLabel :value="$t('accounting.bookings.amount')" />
                <TextInput
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full tabular-nums"
                    placeholder="0,00"
                />
                <InputError :message="form.errors.amount" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel :value="$t('accounting.bookings.category')" />
            <CategorySelect v-model="form.category_id" :categories="categories" :direction="direction" class="mt-1" />
            <InputError :message="form.errors.category_id" class="mt-2" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <InputLabel for="booking_date" :value="$t('accounting.bookings.booking_date')" />
                <DatePicker id="booking_date" v-model="form.booking_date" class="mt-1" />
                <InputError :message="form.errors.booking_date" class="mt-2" />
            </div>
            <div>
                <InputLabel for="valuta_date" :value="$t('accounting.bookings.valuta_date')" />
                <DatePicker id="valuta_date" v-model="form.valuta_date" clearable class="mt-1" />
                <InputError :message="form.errors.valuta_date" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel :value="$t('accounting.bookings.counterparty')" />
            <div class="mt-1 flex gap-0.5 rounded-lg bg-ink/5 p-0.5">
                <button
                    v-for="m in modes"
                    :key="m.value"
                    type="button"
                    class="flex-1 rounded-md px-2 py-1 text-xs font-medium transition"
                    :class="mode === m.value ? 'bg-surface text-ink shadow-sm' : 'text-ink/50 hover:text-ink'"
                    @click="mode = m.value"
                >
                    {{ $t(`accounting.bookings.${m.key}`) }}
                </button>
            </div>
            <select
                v-if="mode === 'child'"
                v-model="form.counterparty_child_id"
                class="mt-2 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
            >
                <option :value="null">{{ $t('accounting.bookings.pick_child') }}</option>
                <option v-for="child in availableChildren" :key="child.id" :value="child.id">{{ child.name }}</option>
            </select>
            <select
                v-if="mode === 'user'"
                v-model="form.counterparty_user_id"
                class="mt-2 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
            >
                <option :value="null">{{ $t('accounting.bookings.pick_user') }}</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
            <TextInput
                v-if="mode === 'free'"
                v-model="form.counterparty_name"
                type="text"
                class="mt-2 block w-full"
            />
            <InputError :message="form.errors.counterparty_child_id" class="mt-2" />
            <InputError :message="form.errors.counterparty_user_id" class="mt-2" />
            <InputError :message="form.errors.counterparty_name" class="mt-2" />
        </div>

        <div>
            <InputLabel for="purpose" :value="$t('accounting.bookings.purpose')" />
            <textarea
                id="purpose"
                v-model="form.purpose"
                rows="2"
                class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
            ></textarea>
            <InputError :message="form.errors.purpose" class="mt-2" />
        </div>

        <div>
            <InputLabel for="comment" :value="$t('accounting.bookings.comment')" />
            <textarea
                id="comment"
                v-model="form.comment"
                rows="2"
                class="mt-1 block w-full rounded-md border-ink/20 shadow-sm focus:border-hort-teal focus:ring-hort-teal"
            ></textarea>
            <InputError :message="form.errors.comment" class="mt-2" />
        </div>
    </div>
</template>
