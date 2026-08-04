<script setup>
import { weeklyPlan, standardPlan } from '@/routes';
import { index as pollsIndex } from '@/routes/polls';
import { confirm as companionConfirm } from '@/routes/companion';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DayEditor from '@/Components/DayEditor.vue';
import WeekNav from '@/Components/WeekNav.vue';
import WeekTimetable from '@/Components/WeekTimetable.vue';
import CompanionNotes from '@/Components/CompanionNotes.vue';
import { t } from '@/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    week: { type: Object, default: () => ({}) },
    weekDays: { type: Array, default: () => [] },
    // { 'YYYY-MM-DD': 'Sommerferien' } — days the Hort is shut.
    closedDays: { type: Object, default: () => ({}) },
    // { 'YYYY-MM-DD': 'Ferienbetreuung' } — days only signed-up children attend.
    careDays: { type: Object, default: () => ({}) },
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

// The prefix to show before a time — only for the meaningful deviations (bis/ab);
// „genau um" is the default and stays implicit to keep the cell clean. Shown for
// „geht allein" and for „geht mit … mit" (which mirrors the companion's qualifier).
function timePrefix(method, qualifier) {
    if ((method !== 'sent_home' && method !== 'with_child') || !qualifier || qualifier === 'at') {
        return '';
    }
    return qualifierPrefix.value[qualifier] ?? '';
}

// The picked week's column headers show the weekday + its date; today is flagged.
const weekColumns = computed(() =>
    props.weekDays.map((d) => ({
        label: d.label,
        sublabel: d.date_label,
        is_today: d.is_today,
        date: d.date,
        closed: props.closedDays[d.date] ?? null,
        care: props.careDays[d.date] ?? null,
    })),
);

// The week's Schließzeiten, named once above the grid rather than in every cell.
const weekClosures = computed(() => [
    ...new Set(props.weekDays.map((d) => props.closedDays[d.date]).filter(Boolean)),
]);

// The week's Ferienbetreuungen, named once above the grid.
const weekCare = computed(() => [
    ...new Set(props.weekDays.map((d) => props.careDays[d.date]).filter(Boolean)),
]);

// Whether a Ferienbetreuung of this week still takes answers — then the banner
// offers the way to the sign-up (also for a family that wants to change theirs).
const careSignupOpen = computed(() =>
    props.currentWeek.some((child) => child.days.some((day) => day.care?.open)),
);

// Most periods are simply called „Ferienbetreuung", and „Ferienbetreuung:
// Ferienbetreuung" reads like a bug. Name them only when the name says more.
const weekCareNames = computed(() =>
    weekCare.value.filter((name) => name.trim().toLowerCase() !== t('weekly.care_short').toLowerCase()),
);

// Mo–Fr all shut → „geschlossen", otherwise „teilweise geschlossen".
const wholeWeekClosed = computed(
    () => props.weekDays.length > 0 && props.weekDays.every((d) => props.closedDays[d.date]),
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
function planClass(day) {
    // „Hortfrei" (no Hort that day): a clearly-visible muted slate chip — distinct from
    // both the coloured pickup days and the amber „reported absent" cells.
    if (!day.time) {
        return 'bg-ink/10 text-ink/60 ring-1 ring-inset ring-ink/10';
    }
    return day.method === 'sent_home'
        ? 'bg-hort-orange/20 text-ink'
        : 'bg-hort-teal/20 text-ink';
}

/**
 * Everything a day cell looks like, decided in one place: a Schließzeit outranks a
 * reported absence, which outranks the planned pickup. `time` says whether to render
 * the 🚶/„ab" prefixes, `extras` whether the companion/birthday/excursion badges
 * belong there at all — on a closed day they'd contradict the „Geschlossen" label.
 */
// A pickup time is short and belongs in the cell's own size; a state („Geschlossen",
// „Nicht angemeldet") is a whole word that has to survive a fifth of a phone screen,
// so it renders smaller and is allowed to wrap.
const STATE_LABEL = 'text-[10px] font-medium leading-tight';

/** „2. August" — for the „Anmeldeschluss war am …" hint. */
function dayMonth(date) {
    return date
        ? new Date(`${date}T00:00:00`).toLocaleDateString(usePage().props.locale || 'de', {
              day: 'numeric',
              month: 'long',
          })
        : '';
}

function cellUi(day, hasPlan = true) {
    if (day.closed) {
        return {
            // Short form: „Geschlossen" doesn't fit a fifth of a phone screen, and the
            // Schließzeit's name is one tap (or hover) away in the title.
            label: t('weekly.closed_short'),
            labelClass: STATE_LABEL,
            title: day.closed,
            class: 'bg-ink/10 text-ink/40',
            time: false,
            extras: false,
        };
    }

    // Ferienbetreuung: not signed up is its own state — the child isn't „frei" that
    // day, they simply aren't coming. There is nothing to edit here; while the
    // Anmeldung runs the cell offers the way to it instead.
    if (day.care && !day.care.registered) {
        return {
            label: t('weekly.care_not_registered'),
            labelClass: STATE_LABEL,
            title: day.care.open
                ? t('weekly.care_not_registered_title')
                : t('weekly.care_signup_closed', { date: dayMonth(day.care.deadline) }),
            class: 'bg-ink/5 text-ink/40 ring-1 ring-inset ring-ink/10',
            time: false,
            extras: false,
        };
    }

    if (day.absent) {
        return {
            label: day.absent.label,
            labelClass: STATE_LABEL,
            title: day.absent.label,
            class: 'bg-amber-100 text-amber-700',
            time: false,
            extras: true,
        };
    }

    // A child whose Stammplan is empty isn't „hortfrei" on every day — nobody has
    // said yet when they go home. The banner above offers to enter it.
    if (!day.time && !hasPlan) {
        return {
            label: t('weekly.no_plan'),
            labelClass: STATE_LABEL,
            title: t('weekly.no_plan_title'),
            class: 'bg-ink/5 text-ink/40 ring-1 ring-inset ring-ink/10',
            time: false,
            extras: false,
        };
    }

    return {
        label: day.time ?? t('weekly.free'),
        labelClass: 'text-sm font-semibold',
        title: day.comment || undefined,
        class: [planClass(day), day.adjusted ? 'ring-2 ring-amber-400' : ''].filter(Boolean).join(' '),
        time: !!day.time,
        extras: true,
    };
}

// „Diese Woche" with each day's presentation resolved once per render.
const decoratedWeek = computed(() =>
    props.currentWeek.map((child) => ({
        ...child,
        days: child.days.map((day) => ({ ...day, ui: cellUi(day, child.has_plan) })),
    })),
);

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

                <!-- Named once for the whole week; the cells themselves just grey out. -->
                <p
                    v-if="weekClosures.length"
                    data-testid="week-closures"
                    class="rounded-2xl bg-ink/5 px-4 py-3 text-sm text-ink/70"
                >
                    {{
                        wholeWeekClosed
                            ? $t('weekly.closed_week_all', { names: weekClosures.join(', ') })
                            : $t('weekly.closed_week', { names: weekClosures.join(', ') })
                    }}
                </p>

                <p
                    v-if="weekCare.length"
                    data-testid="week-care"
                    class="rounded-2xl bg-hort-teal/10 px-4 py-3 text-sm text-ink/70 ring-1 ring-hort-teal/40"
                >
                    {{
                        weekCareNames.length
                            ? $t('weekly.care_week', { names: weekCareNames.join(', ') })
                            : $t('weekly.care_week_plain')
                    }}
                    <!-- Signing up is a fact about the week, not about one row — one
                         link here beats one per child (or, worse, one per cell). -->
                    <Link
                        v-if="careSignupOpen"
                        :href="pollsIndex().url"
                        data-testid="wp-care-signup"
                        class="ml-1 whitespace-nowrap font-medium text-hort-teal-dark underline-offset-2 hover:underline"
                    >
                        {{ $t('weekly.care_signup_link') }} →
                    </Link>
                </p>

                <!-- Parents see + edit their own children; staff use the timeline below. -->
                <template v-if="!isStaff">
                    <h3 class="text-sm font-semibold text-ink/70">{{ $t('weekly.your_children') }}</h3>

                    <ul v-if="currentWeek.length" class="space-y-3">
                    <li
                        v-for="child in decoratedWeek"
                        :key="child.id"
                        class="rounded-2xl bg-surface p-4 shadow-sm"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <p class="font-semibold text-ink">
                                {{ child.name }}
                            </p>
                            <!-- Only promise a tap when some day of the week takes one:
                                 a week nobody is signed up for has nothing to edit. -->
                            <span
                                v-if="child.can_manage && child.days.some((d) => d.editable)"
                                class="text-xs text-ink/40"
                            >
                                {{ $t('weekly.tap_to_change') }}
                            </span>
                        </div>

                        <!-- items-stretch + a growing cell: „Nicht angemeldet" wraps to
                             two lines, and the whole row still lines up. -->
                        <div class="grid grid-cols-5 items-stretch gap-1.5">
                            <div
                                v-for="(day, i) in child.days"
                                :key="day.date"
                                class="flex flex-col rounded-lg text-center"
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
                                    class="relative mt-1 flex w-full grow flex-col justify-center overflow-hidden break-words rounded-lg px-0.5 py-2"
                                    :class="[
                                        day.ui.class,
                                        day.ui.labelClass,
                                        day.editable ? 'cursor-pointer hover:brightness-95 active:scale-[0.97]' : '',
                                    ]"
                                    :title="day.ui.title"
                                    @click="openCell(child, day, weekDays[i])"
                                >
                                    <template v-if="day.ui.time"><span v-if="day.method === 'sent_home'">🚶&nbsp;</span><span v-if="timePrefix(day.method, day.qualifier)">{{ timePrefix(day.method, day.qualifier) }}&nbsp;</span></template>{{ day.ui.label }}
                                    <span
                                        v-if="day.companion && day.ui.extras"
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
                                        v-if="day.birthday !== null && day.ui.extras"
                                        class="mt-0.5 block text-[10px] leading-none"
                                        :title="$t('weekly.birthday_title')"
                                    >
                                        🎂
                                    </span>
                                    <span
                                        v-if="day.excursion && day.ui.extras"
                                        class="mt-0.5 block text-[10px] leading-none"
                                        :title="day.excursion.name"
                                    >
                                        🚌
                                    </span>
                                    <span
                                        v-else-if="day.comment && day.ui.extras"
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
