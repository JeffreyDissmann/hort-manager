<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Avatar from '@/Components/Avatar.vue';
import AppBadge from '@/Components/AppBadge.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import InstallBanner from '@/Components/InstallBanner.vue';
import NotifyPrompt from '@/Components/NotifyPrompt.vue';
import PlanReminderBanner from '@/Components/PlanReminderBanner.vue';
import PullToRefresh from '@/Components/PullToRefresh.vue';
import WhatsNewModal from '@/Components/WhatsNewModal.vue';
import {
    SunIcon,
    CalendarDaysIcon,
    TableCellsIcon,
    UserGroupIcon,
    MapIcon,
    ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';
import { board, weeklyPlan, standardPlan, program, logout, dashboard, help, activityLog } from '@/routes';
import { update as switchRoleRoute } from '@/routes/role';
import { index as childrenIndex } from '@/routes/children';
import { index as excursionsIndex } from '@/routes/excursions';
import { index as usersIndex } from '@/routes/users';
import { index as pollsIndex } from '@/routes/polls';
import { edit as profileEdit } from '@/routes/profile';
import { Link, router, usePage } from '@inertiajs/vue3';
import { t } from '@/i18n';

// Pages with wide weekly editors (Stammplan, Programm) opt into a roomier
// content column on large screens; everything else stays at max-w-5xl.
const props = defineProps({
    wide: { type: Boolean, default: false },
});
const contentMax = computed(() => (props.wide ? 'max-w-7xl' : 'max-w-5xl'));

const whatsNewModal = ref(null);

const appName = computed(() => usePage().props.appName ?? 'Hort-Manager');
const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? '');
const userAvatar = computed(() => user.value?.avatar ?? null);
const isStaff = computed(() => user.value?.role === 'staff');
const isAdmin = computed(() => user.value?.is_admin ?? false);

// Admins can switch their own role (staff ↔ parent) right from the menu.
function switchRole(role) {
    if ((role === 'staff') === isStaff.value) {
        return; // already this role
    }
    router.post(switchRoleRoute().url, { role }, { preserveScroll: true });
}
const pendingPolls = computed(() => usePage().props.pendingPolls ?? 0);
const pendingCompanions = computed(() => usePage().props.pendingCompanions ?? 0);

// Primary navigation — shown as top links on desktop and as a bottom tab bar on mobile.
const navItems = computed(() => {
    // Staff: full nav (Kinder last — changes least). Parents: Heute, Ausflüge, Abholplan.
    const items = isStaff.value
        ? [
              { label: t('common.today'), href: board().url, icon: 'sun' },
              { label: t('nav.excursions'), href: excursionsIndex().url, icon: 'map' },
              { label: t('nav.pickup_plan'), href: weeklyPlan().url, icon: 'calendar' },
              { label: t('nav.program'), href: program().url, icon: 'food' },
              { label: t('nav.children'), href: childrenIndex().url, icon: 'children' },
              { label: t('nav.standard_plan'), href: standardPlan().url, icon: 'table' },
          ]
        : [
              { label: t('common.today'), href: board().url, icon: 'sun' },
              { label: t('nav.excursions'), href: pollsIndex().url, icon: 'map', badge: pendingPolls.value },
              { label: t('nav.pickup_plan'), href: weeklyPlan().url, icon: 'calendar' },
              { label: t('nav.standard_plan'), href: standardPlan().url, icon: 'table' },
          ];

    return items;
});

// Heroicon components keyed by the nav item's icon name.
const icons = {
    sun: SunIcon,
    calendar: CalendarDaysIcon,
    table: TableCellsIcon,
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
    <div class="min-h-[100dvh] bg-canvas">
        <AppBadge />
        <WhatsNewModal ref="whatsNewModal" />
        <InstallBanner />
        <NotifyPrompt />

        <!-- Top bar -->
        <header
            class="sticky top-0 z-20 border-b border-ink/10 bg-surface/90 backdrop-blur"
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
                    <span class="font-display text-2xl text-ink">
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
                                ? 'bg-hort-teal/20 text-ink'
                                : 'text-ink/60 hover:bg-ink/5 hover:text-ink',
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
                            data-testid="user-menu"
                            class="flex items-center gap-2 rounded-full bg-ink/5 py-1.5 pl-1.5 pr-3 text-sm font-medium text-ink transition hover:bg-ink/10"
                        >
                            <Avatar :src="userAvatar" :name="userName" />
                            <span class="hidden max-w-[8rem] truncate sm:inline">
                                {{ userName }}
                            </span>
                        </button>
                    </template>
                    <template #content>
                        <template v-if="isAdmin">
                            <DropdownLink :href="usersIndex().url">
                                {{ $t('nav.users') }}
                            </DropdownLink>
                            <DropdownLink :href="activityLog().url" data-testid="nav-activity-log">
                                {{ $t('nav.activity_log') }}
                            </DropdownLink>
                            <div class="px-4 py-2">
                                <p class="mb-1 text-xs font-medium text-ink/50">{{ $t('nav.my_role') }}</p>
                                <div class="flex gap-0.5 rounded-lg bg-ink/5 p-0.5">
                                    <button
                                        type="button"
                                        data-testid="role-staff"
                                        class="flex-1 rounded-md px-2 py-1 text-xs font-medium transition"
                                        :class="isStaff ? 'bg-surface text-ink shadow-sm' : 'text-ink/50 hover:text-ink'"
                                        @click="switchRole('staff')"
                                    >
                                        {{ $t('enums.user_role.staff') }}
                                    </button>
                                    <button
                                        type="button"
                                        data-testid="role-parent"
                                        class="flex-1 rounded-md px-2 py-1 text-xs font-medium transition"
                                        :class="!isStaff ? 'bg-surface text-ink shadow-sm' : 'text-ink/50 hover:text-ink'"
                                        @click="switchRole('parent')"
                                    >
                                        {{ $t('enums.user_role.parent') }}
                                    </button>
                                </div>
                            </div>
                            <hr class="my-1 border-ink/10" />
                        </template>
                        <DropdownLink
                            v-if="!isStaff"
                            :href="childrenIndex().url"
                        >
                            {{ $t('nav.my_children') }}
                        </DropdownLink>
                        <DropdownLink :href="profileEdit().url">
                            {{ $t('nav.profile') }}
                        </DropdownLink>
                        <DropdownLink :href="help().url">
                            {{ $t('nav.help') }}
                        </DropdownLink>
                        <button
                            type="button"
                            @click="whatsNewModal?.open()"
                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-ink transition duration-150 ease-in-out hover:bg-canvas focus:bg-canvas focus:outline-none"
                        >
                            {{ $t('nav.whats_new') }}
                        </button>
                        <DropdownLink
                            :href="logout().url"
                            method="post"
                            as="button"
                        >
                            {{ $t('nav.logout') }}
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
                    {{
                        pendingPolls > 1
                            ? $t('nav.pending_polls_plural', { n: pendingPolls })
                            : $t('nav.pending_polls', { n: pendingPolls })
                    }}
                </span>
                <span class="ml-auto">→</span>
            </div>
        </Link>

        <!-- Pending „geht mit … mit" confirmation (parents) -->
        <Link
            v-if="!isStaff && pendingCompanions > 0"
            :href="weeklyPlan().url"
            class="block bg-hort-orange text-hort-navy"
        >
            <div
                :class="contentMax"
                class="mx-auto flex items-center gap-2 px-4 py-2.5 text-sm font-semibold sm:px-6"
            >
                <span class="text-lg">🚸</span>
                <span>
                    {{
                        pendingCompanions > 1
                            ? $t('nav.pending_companions_plural', { n: pendingCompanions })
                            : $t('nav.pending_companions', { n: pendingCompanions })
                    }}
                </span>
                <span class="ml-auto">→</span>
            </div>
        </Link>

        <!-- Page heading -->
        <header v-if="$slots.header" class="bg-surface">
            <div :class="contentMax" class="mx-auto px-4 py-6 sm:px-6">
                <slot name="header" />
            </div>
        </header>

        <!-- Page content (extra bottom padding so the mobile tab bar never covers it) -->
        <main
            :class="contentMax"
            class="mx-auto px-4 pb-28 pt-6 sm:px-6 sm:pb-12"
        >
            <PullToRefresh>
                <PlanReminderBanner class="mb-4" />
                <slot />
            </PullToRefresh>
        </main>

        <!-- Mobile bottom tab bar -->
        <nav
            class="fixed inset-x-0 bottom-0 z-20 border-t border-ink/10 bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur sm:hidden"
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
                            : 'text-ink/50',
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
