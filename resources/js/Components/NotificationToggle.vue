<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    ariaLabel: { type: String, default: '' },
});

defineEmits(['update:modelValue', 'change']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="ariaLabel"
        :disabled="disabled"
        @click="
            $emit('update:modelValue', !modelValue);
            $emit('change');
        "
        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-40"
        :class="modelValue ? 'bg-hort-teal-dark' : 'bg-ink/25'"
    >
        <span
            class="inline-block h-4 w-4 transform rounded-full bg-surface transition"
            :class="modelValue ? 'translate-x-6' : 'translate-x-1'"
        />
    </button>
</template>
