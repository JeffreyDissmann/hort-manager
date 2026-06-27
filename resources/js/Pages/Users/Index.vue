<script setup>
import { update as usersUpdate } from '@/routes/users';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    <Head title="Benutzer" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Benutzer</h2>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <p class="text-sm text-hort-navy/60">
                Rolle = Zugriff in der App (Erzieher:in oder Elternteil).
                Admin = darf Benutzer verwalten. Beides ist unabhängig.
            </p>

            <ul class="space-y-3">
                <li
                    v-for="user in users"
                    :key="user.id"
                    class="flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm"
                >
                    <Avatar :src="user.avatar" :name="user.name" />

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-hort-navy">
                            {{ user.name }}
                            <span v-if="user.is_self" class="text-hort-teal-dark">· du</span>
                        </p>
                        <p class="truncate text-sm text-hort-navy/50">{{ user.email }}</p>
                    </div>

                    <select
                        :value="user.role"
                        @change="(e) => save(user, { role: e.target.value })"
                        class="rounded-lg border-gray-300 text-sm text-hort-navy focus:border-hort-teal focus:ring-hort-teal"
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
                        class="flex cursor-pointer items-center gap-2 rounded-lg bg-hort-navy/5 px-3 py-2 text-sm font-medium text-hort-navy"
                    >
                        <input
                            type="checkbox"
                            :checked="user.is_admin"
                            @change="(e) => save(user, { is_admin: e.target.checked })"
                            class="rounded border-gray-300 text-hort-teal-dark focus:ring-hort-teal"
                        />
                        Admin
                    </label>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
