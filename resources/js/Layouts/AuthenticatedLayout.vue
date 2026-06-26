<script setup>
import { computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import {
    SunIcon,
    CalendarDaysIcon,
    UserGroupIcon,
    MapIcon,
    ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';
import { Link, usePage } from '@inertiajs/vue3';

const appName = computed(() => usePage().props.appName ?? 'Hort-Manager');
const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? '');
const isStaff = computed(() => user.value?.role === 'staff');
const pendingPolls = computed(() => usePage().props.pendingPolls ?? 0);

// Primary navigation — shown as top links on desktop and as a bottom tab bar on mobile.
const navItems = computed(() => {
    if (isStaff.value) {
        // Kinder last — it's the thing that changes least often.
        return [
            { label: 'Heute', route: 'board', pattern: 'board', icon: 'sun' },
            { label: 'Ausflüge', route: 'excursions.index', pattern: 'excursions.*', icon: 'map' },
            { label: 'Abholplan', route: 'weekly-plan', pattern: 'weekly-plan', icon: 'calendar' },
            { label: 'Programm', route: 'program', pattern: 'program', icon: 'food' },
            { label: 'Kinder', route: 'children.index', pattern: 'children.*', icon: 'children' },
        ];
    }

    // Parents: Heute, Ausflüge (poll badge), Abholplan; „Meine Kinder" lives in the user menu.
    return [
        { label: 'Heute', route: 'board', pattern: 'board', icon: 'sun' },
        { label: 'Ausflüge', route: 'polls.index', pattern: 'polls.*', icon: 'map', badge: pendingPolls.value },
        { label: 'Abholplan', route: 'weekly-plan', pattern: 'weekly-plan', icon: 'calendar' },
    ];
});

// Heroicon components keyed by the nav item's icon name.
const icons = {
    sun: SunIcon,
    calendar: CalendarDaysIcon,
    children: UserGroupIcon,
    map: MapIcon,
    food: ClipboardDocumentListIcon,
};

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
                        <DropdownLink
                            v-if="!isStaff"
                            :href="route('children.index')"
                        >
                            Meine Kinder
                        </DropdownLink>
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

        <!-- Pending poll notification (parents) -->
        <Link
            v-if="!isStaff && pendingPolls > 0"
            :href="route('polls.index')"
            class="block bg-amber-400 text-hort-navy"
        >
            <div
                class="mx-auto flex max-w-5xl items-center gap-2 px-4 py-2.5 text-sm font-semibold sm:px-6"
            >
                <span class="text-lg">📣</span>
                <span>
                    {{ pendingPolls }} offene Ausflug-Abstimmung{{
                        pendingPolls > 1 ? 'en' : ''
                    }}
                    – bitte antworten
                </span>
                <span class="ml-auto">→</span>
            </div>
        </Link>

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
                    <span class="relative">
                        <component :is="icons[item.icon]" class="h-6 w-6" />
                        <span
                            v-if="item.badge"
                            class="absolute -right-2 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-bold text-white"
                        >
                            {{ item.badge }}
                        </span>
                    </span>
                    {{ item.label }}
                </Link>
            </div>
        </nav>
    </div>
</template>
