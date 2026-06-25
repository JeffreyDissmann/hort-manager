<script setup>
import { computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const appName = computed(() => usePage().props.appName ?? 'Hort-Manager');
const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? '');
const isStaff = computed(() => user.value?.role === 'staff');

// Primary navigation — shown as top links on desktop and as a bottom tab bar on mobile.
const navItems = computed(() => [
    { label: 'Start', route: 'dashboard', pattern: 'dashboard', icon: 'home' },
    {
        label: isStaff.value ? 'Kinder' : 'Meine Kinder',
        route: 'children.index',
        pattern: 'children.*',
        icon: 'children',
    },
]);

function isActive(pattern) {
    return route().current(pattern);
}
</script>

<template>
    <div class="min-h-[100dvh] bg-hort-sand">
        <!-- Top bar -->
        <header
            class="sticky top-0 z-20 border-b border-hort-navy/10 bg-white/90 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4 sm:px-6"
            >
                <Link
                    :href="route('dashboard')"
                    class="flex items-center gap-2"
                >
                    <ApplicationLogo class="h-9 w-9" />
                    <span class="font-display text-2xl text-hort-navy">
                        {{ appName }}
                    </span>
                </Link>

                <!-- Desktop nav links -->
                <nav class="hidden items-center gap-1 sm:flex">
                    <Link
                        v-for="item in navItems"
                        :key="item.route"
                        :href="route(item.route)"
                        :class="[
                            'rounded-lg px-3 py-2 text-sm font-medium transition',
                            isActive(item.pattern)
                                ? 'bg-hort-teal/20 text-hort-navy'
                                : 'text-hort-navy/60 hover:bg-hort-navy/5 hover:text-hort-navy',
                        ]"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <!-- User menu -->
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-full bg-hort-navy/5 py-1.5 pl-1.5 pr-3 text-sm font-medium text-hort-navy transition hover:bg-hort-navy/10"
                        >
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-hort-teal text-sm font-semibold text-hort-navy"
                            >
                                {{ userName.charAt(0).toUpperCase() }}
                            </span>
                            <span class="hidden max-w-[8rem] truncate sm:inline">
                                {{ userName }}
                            </span>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">
                            Profil
                        </DropdownLink>
                        <DropdownLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Abmelden
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </header>

        <!-- Page heading -->
        <header v-if="$slots.header" class="bg-white">
            <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <slot name="header" />
            </div>
        </header>

        <!-- Page content (extra bottom padding so the mobile tab bar never covers it) -->
        <main class="mx-auto max-w-5xl px-4 pb-28 pt-6 sm:px-6 sm:pb-12">
            <slot />
        </main>

        <!-- Mobile bottom tab bar -->
        <nav
            class="fixed inset-x-0 bottom-0 z-20 border-t border-hort-navy/10 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur sm:hidden"
        >
            <div class="mx-auto flex max-w-md items-stretch justify-around">
                <Link
                    v-for="item in navItems"
                    :key="item.route"
                    :href="route(item.route)"
                    :class="[
                        'flex flex-1 flex-col items-center gap-1 py-2.5 text-xs font-medium transition',
                        isActive(item.pattern)
                            ? 'text-hort-teal-dark'
                            : 'text-hort-navy/50',
                    ]"
                >
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.8"
                        stroke="currentColor"
                    >
                        <path
                            v-if="item.icon === 'home'"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 12l8.954-8.955a1.5 1.5 0 012.121 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"
                        />
                        <path
                            v-else
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                        />
                    </svg>
                    {{ item.label }}
                </Link>
            </div>
        </nav>
    </div>
</template>
