<script setup>
import { computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Avatar from '@/Components/Avatar.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import {
    SunIcon,
    CalendarDaysIcon,
    UserGroupIcon,
    MapIcon,
    ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';
import { board, weeklyPlan, program, logout, dashboard } from '@/routes';
import { index as childrenIndex } from '@/routes/children';
import { index as excursionsIndex } from '@/routes/excursions';
import { index as pollsIndex } from '@/routes/polls';
import { edit as profileEdit } from '@/routes/profile';
import { Link, usePage } from '@inertiajs/vue3';

// Pages with wide weekly editors (Stammplan, Programm) opt into a roomier
// content column on large screens; everything else stays at max-w-5xl.
const props = defineProps({
    wide: { type: Boolean, default: false },
});
const contentMax = computed(() => (props.wide ? 'max-w-7xl' : 'max-w-5xl'));

const appName = computed(() => usePage().props.appName ?? 'Hort-Manager');
const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? '');
const userAvatar = computed(() => user.value?.avatar ?? null);
const isStaff = computed(() => user.value?.role === 'staff');
const pendingPolls = computed(() => usePage().props.pendingPolls ?? 0);

// Primary navigation — shown as top links on desktop and as a bottom tab bar on mobile.
const navItems = computed(() => {
    if (isStaff.value) {
        // Kinder last — it's the thing that changes least often.
        return [
            { label: 'Heute', href: board().url, icon: 'sun' },
            { label: 'Ausflüge', href: excursionsIndex().url, icon: 'map' },
            { label: 'Abholplan', href: weeklyPlan().url, icon: 'calendar' },
            { label: 'Programm', href: program().url, icon: 'food' },
            { label: 'Kinder', href: childrenIndex().url, icon: 'children' },
        ];
    }

    // Parents: Heute, Ausflüge (poll badge), Abholplan; „Meine Kinder" lives in the user menu.
    return [
        { label: 'Heute', href: board().url, icon: 'sun' },
        { label: 'Ausflüge', href: pollsIndex().url, icon: 'map', badge: pendingPolls.value },
        { label: 'Abholplan', href: weeklyPlan().url, icon: 'calendar' },
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

// Active tab = current path equals the item's href or is a sub-path of it.
const currentPath = computed(() => usePage().url.split('?')[0]);
function isActive(href) {
    return currentPath.value === href || currentPath.value.startsWith(href + '/');
}
</script>

<template>
    <div class="min-h-[100dvh] bg-hort-sand">
        <!-- Top bar -->
        <header
            class="sticky top-0 z-20 border-b border-hort-navy/10 bg-white/90 backdrop-blur"
        >
            <div
                :class="contentMax"
                class="mx-auto flex h-16 items-center justify-between px-4 sm:px-6"
            >
                <Link
                    :href="dashboard().url"
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
                        :key="item.label"
                        :href="item.href"
                        :class="[
                            'rounded-lg px-3 py-2 text-sm font-medium transition',
                            isActive(item.href)
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
                            <Avatar :src="userAvatar" :name="userName" />
                            <span class="hidden max-w-[8rem] truncate sm:inline">
                                {{ userName }}
                            </span>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink
                            v-if="!isStaff"
                            :href="childrenIndex().url"
                        >
                            Meine Kinder
                        </DropdownLink>
                        <DropdownLink :href="profileEdit().url">
                            Profil
                        </DropdownLink>
                        <DropdownLink
                            :href="logout().url"
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
            :href="pollsIndex().url"
            class="block bg-amber-400 text-hort-navy"
        >
            <div
                :class="contentMax"
                class="mx-auto flex items-center gap-2 px-4 py-2.5 text-sm font-semibold sm:px-6"
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
            <div :class="contentMax" class="mx-auto px-4 py-6 sm:px-6">
                <slot name="header" />
            </div>
        </header>

        <!-- Page content (extra bottom padding so the mobile tab bar never covers it) -->
        <main
            :class="contentMax"
            class="mx-auto px-4 pb-28 pt-6 sm:px-6 sm:pb-12"
        >
            <slot />
        </main>

        <!-- Mobile bottom tab bar -->
        <nav
            class="fixed inset-x-0 bottom-0 z-20 border-t border-hort-navy/10 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur sm:hidden"
        >
            <div class="mx-auto flex max-w-md items-stretch justify-around">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    :class="[
                        'flex flex-1 flex-col items-center gap-1 py-2.5 text-xs font-medium transition',
                        isActive(item.href)
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
