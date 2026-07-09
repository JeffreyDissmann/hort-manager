<script setup>
import { update as usersUpdate, sync as usersSync, destroy as usersDestroy } from '@/routes/users';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import { ArrowPathIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
});

const flash = computed(() => usePage().props.flash?.status);

const syncing = ref(false);

function syncFromSlack() {
    router.post(
        usersSync().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (syncing.value = true),
            onFinish: () => (syncing.value = false),
        },
    );
}

function destroy(user) {
    if (confirm(t('users.delete_confirm', { name: user.name }))) {
        router.delete(usersDestroy(user.id).url, { preserveScroll: true });
    }
}

// Role and admin are independent — patch whichever changed, keep the other.
function save(user, changes) {
    router.patch(
        usersUpdate(user.id).url,
        {
            role: changes.role ?? user.role,
            is_admin: changes.is_admin ?? user.is_admin,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="$t('users.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('users.title') }}</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-ink/60">
                    {{ $t('users.intro') }}
                </p>
                <button
                    type="button"
                    @click="syncFromSlack"
                    :disabled="syncing"
                    class="flex shrink-0 items-center gap-2 rounded-xl bg-hort-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-hort-navy/90 disabled:opacity-60"
                >
                    <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': syncing }" />
                    {{ $t('users.import_from_slack') }}
                </button>
            </div>

            <ul class="space-y-3">
                <li
                    v-for="user in users"
                    :key="user.id"
                    class="flex flex-wrap items-center gap-3 rounded-2xl bg-surface p-4 shadow-sm"
                >
                    <Avatar :src="user.avatar" :name="user.name" />

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink">
                            {{ user.name }}
                            <span v-if="user.is_self" class="text-hort-teal-dark">· {{ $t('users.self_suffix') }}</span>
                        </p>
                        <p class="truncate text-sm text-ink/50">{{ user.email }}</p>
                        <p v-if="user.children.length" class="mt-1 flex flex-wrap items-center gap-1">
                            <span class="text-xs text-ink/40">{{ $t('users.children_label') }}:</span>
                            <span
                                v-for="name in user.children"
                                :key="name"
                                class="rounded bg-hort-teal/15 px-1.5 py-0.5 text-xs font-medium text-hort-teal-dark"
                            >
                                {{ name }}
                            </span>
                        </p>
                    </div>

                    <select
                        :value="user.role"
                        @change="(e) => save(user, { role: e.target.value })"
                        class="rounded-lg border-ink/20 text-sm text-ink focus:border-hort-teal focus:ring-hort-teal"
                    >
                        <option
                            v-for="option in roleOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <label
                        class="flex cursor-pointer items-center gap-2 rounded-lg bg-ink/5 px-3 py-2 text-sm font-medium text-ink"
                    >
                        <input
                            type="checkbox"
                            :checked="user.is_admin"
                            @change="(e) => save(user, { is_admin: e.target.checked })"
                            class="rounded border-ink/20 text-hort-teal-dark focus:ring-hort-teal"
                        />
                        {{ $t('users.admin') }}
                    </label>

                    <button
                        v-if="!user.is_self"
                        type="button"
                        @click="destroy(user)"
                        class="shrink-0 rounded-lg p-2 text-ink/30 transition hover:bg-red-50 hover:text-red-600"
                        :aria-label="$t('users.delete_aria')"
                    >
                        <TrashIcon class="h-5 w-5" />
                    </button>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
