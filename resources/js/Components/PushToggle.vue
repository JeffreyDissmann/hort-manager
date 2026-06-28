<script setup>
import { usePush } from '@/composables/usePush';
import { onMounted } from 'vue';

const { supported, subscribed, busy, refresh, enable, disable } = usePush();

onMounted(refresh);
</script>

<template>
    <button
        v-if="supported"
        type="button"
        :disabled="busy"
        @click="subscribed ? disable() : enable()"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-hort-navy transition duration-150 ease-in-out hover:bg-hort-sand focus:bg-hort-sand focus:outline-none disabled:opacity-50"
    >
        {{ subscribed ? '🔕 Benachrichtigungen aus' : '🔔 Benachrichtigungen an' }}
    </button>
</template>
