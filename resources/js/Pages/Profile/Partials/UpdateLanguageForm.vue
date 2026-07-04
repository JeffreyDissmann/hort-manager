<script setup>
import { update as localeUpdate } from '@/routes/locale';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const locales = computed(() => page.props.locales ?? {});
const selected = ref(page.props.locale);

function change() {
    router.patch(
        localeUpdate().url,
        { locale: selected.value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-hort-navy">{{ $t('profile.language') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $t('profile.language_help') }}</p>
        </header>

        <div class="mt-6 max-w-xl">
            <select
                v-model="selected"
                class="block w-full rounded-md border-gray-300 text-hort-navy shadow-sm focus:border-hort-teal focus:ring-hort-teal sm:max-w-xs"
                @change="change"
            >
                <option v-for="(label, code) in locales" :key="code" :value="code">
                    {{ label }}
                </option>
            </select>
        </div>
    </section>
</template>
