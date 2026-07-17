<script setup>
import { weeklyPlan, standardPlan } from '@/routes';
import { confirm as companionConfirm } from '@/routes/companion';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DayEditor from '@/Components/DayEditor.vue';
import WeekNav from '@/Components/WeekNav.vue';
import WeekTimetable from '@/Components/WeekTimetable.vue';
import CompanionNotes from '@/Components/CompanionNotes.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    week: { type: Object, default: () => ({}) },
    weekDays: { type: Array, default: () => [] },
    currentWeek: { type: Array, default: () => [] },
    activities: { type: Array, default: () => [] },
    program: { type: Array, default: () => [] },
    weekTimetable: { type: Array, default: () => [] },
    weekAbsences: { type: Array, default: () => [] },
    weekHortfrei: { type: Array, default: () => [] },
    children: { type: Array, default: () => [] },
    companionNotes: { type: Array, default: () => [] },
    methodOptions: { type: Array, default: () => [] },
    qualifierOptions: { type: Array, default: () => [] },
});

// „Kind (Grund – Kommentar)" per day, for the not-coming summary under the grid.
function absenceLine(day) {
    return day
        .map((a) => (a.comment ? `${a.name} (${a.label} – ${a.comment})` : `${a.name} (${a.label})`))
        .join(', ');
}

// Per weekday, everyone who isn't at the Hort: reported-absent + regularly „Hortfrei".
const notThereDays = computed(() =>
    props.weekAbsences
        .map((absent, i) => ({ i, absent, hortfrei: props.weekHortfrei[i] ?? [] }))
        .filter((d) => d.absent.length || d.hortfrei.length),
);

// Short prefix per „geht allein" time qualifier (bis/um/ab), keyed by value.
const qualifierPrefix = computed(() =>
    Object.fromEntries(props.qualifierOptions.map((o) => [o.value, o.prefix])),
);

// The prefix to show before a sent_home time — only for the meaningful deviations
// (bis/ab); „genau um" is the default and stays implicit to keep the cell clean.
function timePrefix(method, qualifier) {
    if (method !== 'sent_home' || !qualifier || qualifier === 'at') {
        return '';
    }
    return qualifierPrefix.value[qualifier] ?? '';
}

// The picked week's column headers show the weekday + its date; today is flagged.
const weekColumns = computed(() =>
    props.weekDays.map((d) => ({ label: d.label, sublabel: d.date_label, is_today: d.is_today, date: d.date })),
);

function goWeek(date) {
    router.get(
        weeklyPlan(date ? { query: { week: date } } : {}).url,
        {},
        { preserveScroll: true },
    );
}

// Swipe left/right to move between weeks.
let touchStartX = 0;
function onTouchStart(e) {
    touchStartX = e.changedTouches[0].clientX;
}
function onTouchEnd(e) {
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 60) {
        goWeek(dx < 0 ? props.week.next : props.week.prev);
    }
}

const flash = computed(() => usePage().props.flash?.status);
const isStaff = computed(() => usePage().props.auth?.user?.role === 'staff');

function toMinutes(time) {
    return parseInt(time.slice(0, 2), 10) * 60 + parseInt(time.slice(3, 5), 10);
}

// Pickup falls inside that day's homework slot.
function homeworkConflict(day, i) {
    const hw = props.program[i];
    if (!day.time || !hw || !hw.homework_start || !hw.homework_end) {
        return false;
    }
    const pickup = toMinutes(day.time);
    return pickup >= toMinutes(hw.homework_start) && pickup < toMinutes(hw.homework_end);
}

// Solid `ink` time; the method reads from the warm/cool tint, and the "goes home
// alone" case additionally gets a 🚶 icon.
function cellClass(day) {
    // „Hortfrei" (no Hort that day): a clearly-visible muted slate chip — distinct from
    // both the coloured pickup days and the amber „reported absent" cells.
    if (!day.time) {
        return 'bg-ink/10 text-ink/60 ring-1 ring-inset ring-ink/10';
    }
    return day.method === 'sent_home'
        ? 'bg-hort-orange/20 text-ink'
        : 'bg-hort-teal/20 text-ink';
}

// --- Day editor (shared popup) ---
const dayEditor = ref(null);

function openCell(child, day, dayMeta) {
    dayEditor.value?.open(child, day, dayMeta);
}

// Open the day editor for a „Hortfrei" child straight from the „nicht da" summary.
function openHortfreiDay(dayIndex, entry) {
    const child = props.currentWeek.find((c) => c.id === entry.id);
    if (child) {
        openCell(child, child.days[dayIndex], props.weekDays[dayIndex]);
    }
}

// Staff editing a pickup from the Ganze-Woche timeline (kid carries the day data).
function openFromTimeline(kid, column) {
    openCell(kid, kid, { label: column.label, date_label: column.sublabel });
}

// Confirm/decline another child going home with one of ours (companion's guardian/staff).
function answerCompanion(id, confirmed) {
    router.patch(companionConfirm(id).url, { confirmed }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('weekly.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-ink">{{ $t('weekly.title') }}</h2>
        </template>

        <div class="space-y-8">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-ink"
            >
                {{ flash }}
            </div>

            <!-- „Geht mit … mit" overview for the parent: their child going with another
                 (+ status), or a child coming home with theirs (confirm inline). -->
            <CompanionNotes :notes="companionNotes" @confirm="answerCompanion" />

            <!-- Current week (effective plan, editable) -->
            <section class="space-y-3" @touchstart="onTouchStart" @touchend="onTouchEnd">
                <WeekNav :week="week" @navigate="goWeek" />

                <!-- Parents see + edit their own children; staff use the timeline below. -->
                <template v-if="!isStaff">
                    <h3 class="text-sm font-semibold text-ink/70">{{ $t('weekly.your_children') }}</h3>

                    <ul v-if="currentWeek.length" class="space-y-3">
                    <li
                        v-for="child in currentWeek"
                        :key="child.id"
                        class="rounded-2xl bg-surface p-4 shadow-sm"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <p class="font-semibold text-ink">
                                {{ child.name }}
                            </p>
                            <span
                                v-if="child.can_manage"
                                class="text-xs text-ink/40"
                            >
                                {{ $t('weekly.tap_to_change') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-5 gap-1.5">
                            <div
                                v-for="(day, i) in child.days"
                                :key="day.date"
                                class="rounded-lg text-center"
                                :class="[
                                    day.past ? 'opacity-40' : '',
                                    weekDays[i].is_today ? 'bg-hort-teal/10 ring-1 ring-hort-teal/40' : '',
                                ]"
                            >
                                <div
                                    class="text-xs font-medium"
                                    :class="weekDays[i].is_today ? 'text-hort-teal-dark' : 'text-ink/40'"
                                >
                                    {{ weekDays[i].label }}<span v-if="weekDays[i].is_today"> · {{ $t('common.today') }}</span>
                                </div>
                                <div
                                    class="text-[11px]"
                                    :class="weekDays[i].is_today ? 'font-semibold text-hort-teal-dark' : 'text-ink/30'"
                                >
                                    {{ weekDays[i].date_label }}
                                </div>
                                <component
                                    :is="day.editable ? 'button' : 'div'"
                                    type="button"
                                    :data-testid="`wp-cell-${child.id}-${day.date}`"
                                    class="relative mt-1 w-full rounded-lg py-2 text-sm font-semibold"
                                    :class="[
                                        day.absent ? 'bg-amber-100 text-amber-700' : cellClass(day),
                                        day.adjusted && !day.absent ? 'ring-2 ring-amber-400' : '',
                                        day.editable ? 'cursor-pointer hover:brightness-95 active:scale-[0.97]' : '',
                                    ]"
                                    :title="day.absent ? day.absent.label : day.comment || undefined"
                                    @click="openCell(child, day, weekDays[i])"
                                >
                                    <template v-if="!day.absent && day.time"><span v-if="day.method === 'sent_home'">🚶&nbsp;</span><span v-if="timePrefix(day.method, day.qualifier)">{{ timePrefix(day.method, day.qualifier) }}&nbsp;</span></template>{{ day.absent ? day.absent.label : (day.time ?? $t('weekly.free')) }}
                                    <span
                                        v-if="day.companion"
                                        class="mt-0.5 block truncate text-[10px] font-normal leading-tight"
                                        :class="[
                                            day.companion.confirmed === true ? 'opacity-70' : 'font-medium',
                                            day.companion.confirmed === false ? 'text-red-700' : '',
                                            day.companion.confirmed === null ? 'text-hort-orange-dark' : '',
                                        ]"
                                    >
                                        {{ $t('weekly.companion_with', { name: day.companion.name }) }}<template v-if="day.companion.confirmed === null"> · {{ $t('weekly.companion_pending') }}</template><template v-else-if="day.companion.confirmed === false"> · {{ $t('weekly.companion_declined') }}</template>
                                    </span>
                                    <span
                                        v-if="day.birthday !== null"
                                        class="mt-0.5 block text-[10px] leading-none"
                                        :title="$t('weekly.birthday_title')"
                                    >
                                        🎂
                                    </span>
                                    <span
                                        v-if="day.excursion"
                                        class="mt-0.5 block text-[10px] leading-none"
                                        :title="day.excursion.name"
                                    >
                                        🚌
                                    </span>
                                    <span
                                        v-else-if="day.comment"
                                        class="mt-0.5 block truncate text-[10px] font-normal leading-tight opacity-70"
                                    >
                                        {{ day.comment }}
                                    </span>
                                </component>
                            </div>
                        </div>

                        <!-- Birthdays, trips and pickup conflicts this week -->
                        <div
                            v-if="child.days.some((d, idx) => d.excursion || d.birthday !== null || homeworkConflict(d, idx))"
                            class="mt-2 space-y-1"
                        >
                            <template v-for="(day, i) in child.days" :key="day.date">
                                <p
                                    v-if="day.birthday !== null"
                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                                >
                                    {{ $t('weekly.birthday_flag', { day: weekDays[i].label, age: day.birthday }) }}
                                </p>
                                <p
                                    v-if="day.conflict"
                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                                >
                                    {{ $t('weekly.pickup_conflict', { day: weekDays[i].label, time: day.time, name: day.excursion.name }) }}<span
                                        v-if="day.excursion.return_at"
                                    >
                                        ({{ $t('weekly.back_return', { time: day.excursion.return_at }) }})</span
                                    >
                                </p>
                                <p
                                    v-else-if="day.excursion"
                                    class="rounded-lg bg-hort-purple/10 px-2 py-1 text-xs font-medium text-hort-purple"
                                >
                                    {{ $t('weekly.excursion_flag', { day: weekDays[i].label, name: day.excursion.name }) }}<span
                                        v-if="day.excursion.depart_at"
                                    >
                                        ({{ day.excursion.depart_at }}–{{
                                            day.excursion.return_at
                                        }})</span
                                    >
                                </p>
                                <p
                                    v-if="homeworkConflict(day, i)"
                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                                >
                                    {{ $t('weekly.homework_conflict', { day: weekDays[i].label, time: day.time }) }}
                                </p>
                            </template>
                        </div>
                    </li>
                </ul>

                    <p
                        v-else
                        class="rounded-2xl border-2 border-dashed border-ink/15 p-6 text-center text-sm text-ink/50"
                    >
                        {{ $t('weekly.no_child_assigned') }}
                    </p>
                </template>

                <!-- Whole week, all children: effective plan + this week's programs -->
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-ink/70">
                        {{ $t('weekly.whole_week') }}
                    </h3>
                    <WeekTimetable
                        v-if="weekTimetable.length || program.some((p) => p && (p.lunch || p.activity || p.homework_start))"
                        :rows="weekTimetable"
                        :columns="weekColumns"
                        :program="program"
                        :activities="activities"
                        :editable="isStaff"
                        @edit="openFromTimeline"
                    />
                    <p
                        v-else
                        class="rounded-2xl border-2 border-dashed border-ink/15 p-6 text-center text-sm text-ink/50"
                    >
                        {{ $t('weekly.empty_week') }}
                    </p>

                    <!-- Not at the Hort this week: reported absences (amber) + regularly
                         „Hortfrei" (muted) — the latter don't appear on the grid above. -->
                    <div
                        v-if="notThereDays.length"
                        class="rounded-2xl bg-ink/5 p-3 shadow-sm"
                    >
                        <p class="mb-1 text-sm font-semibold text-ink/70">{{ $t('weekly.not_coming_heading') }}</p>
                        <p
                            v-for="d in notThereDays"
                            :key="d.i"
                            class="flex gap-2 text-xs leading-relaxed"
                        >
                            <span class="w-8 shrink-0 pt-0.5 font-semibold text-ink/70">{{ weekColumns[d.i].label }}:</span>
                            <span class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-1">
                                <span v-if="d.absent.length" class="text-amber-800">{{ absenceLine(d.absent) }}</span>
                                <span v-if="d.absent.length && d.hortfrei.length" class="text-ink/30">·</span>
                                <template v-if="d.hortfrei.length">
                                    <span class="text-ink/50">{{ $t('weekly.free') }}:</span>
                                    <template v-for="c in d.hortfrei" :key="c.id">
                                        <button
                                            v-if="c.can_manage"
                                            type="button"
                                            class="rounded-md bg-ink/10 px-1.5 py-0.5 font-medium text-ink/60 transition hover:bg-ink/20 hover:text-ink"
                                            @click="openHortfreiDay(d.i, c)"
                                        >
                                            {{ c.name }}
                                        </button>
                                        <span v-else class="text-ink/50">{{ c.name }}</span>
                                    </template>
                                </template>
                            </span>
                        </p>
                    </div>
                </div>

                <div
                    v-if="currentWeek.length"
                    class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-ink/60"
                >
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-hort-teal/60" />
                        {{ $t('weekly.legend_picked_up') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-hort-orange/60" />
                        🚶 {{ $t('weekly.legend_alone') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full ring-2 ring-amber-400" />
                        {{ $t('weekly.legend_changed') }}
                    </span>
                </div>
            </section>

            <!-- Pointer to the standard plan (edit the regular weekly times there) -->
            <p class="border-t border-ink/5 pt-4 text-center text-sm text-ink/50">
                {{ $t('weekly.to_standard_hint') }}
                <Link
                    :href="standardPlan().url"
                    class="font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                >
                    {{ $t('weekly.to_standard_link') }} →
                </Link>
            </p>
        </div>

        <DayEditor
            ref="dayEditor"
            :children="children"
            :method-options="methodOptions"
            :qualifier-options="qualifierOptions"
        />
    </AuthenticatedLayout>
</template>
