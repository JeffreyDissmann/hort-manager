<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { board } from '@/routes';

const props = defineProps({
    // Rows: [{ time, days: [ [ {id,name,method,comment,adjusted?,excursion?} ] × 5 ] }]
    rows: { type: Array, default: () => [] },
    // Column headers: [{ label, sublabel? }] × 5
    columns: { type: Array, default: () => [] },
    // Per-day program: [{ lunch, activity, homework_start, homework_end }] × 5
    program: { type: Array, default: () => [] },
    // Per-day excursions: [ [ {name, depart_at, return_at} ] ] × 5
    activities: { type: Array, default: () => [] },
    // When true, pickups that are editable become clickable (emits `edit`).
    editable: { type: Boolean, default: false },
});

const emit = defineEmits(['edit']);

function toMin(t) {
    return parseInt(t.slice(0, 2), 10) * 60 + parseInt(t.slice(3, 5), 10);
}
const bucket = (m) => Math.floor(m / 30) * 30;

// Grid track numbers per day j (0–4): one shared band lane (Ausflug + Hausaufgaben
// never overlap in time), then the pickups.
const bandCol = (j) => 2 + j * 2;
const kidsCol = (j) => 3 + j * 2;

const slotMins = computed(() => props.rows.map((r) => toMin(r.time)));

// Full-height grid span (header + one line per time slot). `1 / -1` doesn't work here
// because the rows are implicit (no gridTemplateRows), so -1 collapses to the first row.
const fullColumn = computed(() => `1 / ${props.rows.length + 2}`);

// A band's CSS `grid-row` (start line / span), or null if it can't be placed.
function bandRow(startStr, endStr) {
    if (!startStr) {
        return null;
    }
    const start = bucket(toMin(startStr));
    const lastSlot = bucket((endStr ? toMin(endStr) : toMin(startStr) + 30) - 1);
    const mins = slotMins.value;
    let s = mins.indexOf(start);
    if (s === -1) {
        s = 0;
    }
    let e = mins.indexOf(lastSlot);
    if (e === -1) {
        e = mins.length - 1;
    }
    return `${s + 2} / span ${Math.max(1, e - s + 1)}`;
}

// The child's name is always solid `ink`. The method reads from the warm/cool tint;
// the safety-relevant "goes home alone" case additionally gets a 🚶 icon.
function chipClass(method) {
    return method === 'sent_home'
        ? 'bg-hort-orange/20 text-ink'
        : 'bg-hort-teal/20 text-ink';
}
</script>

<template>
    <div class="overflow-x-auto rounded-2xl bg-surface p-2 shadow-sm">
        <div
            class="grid min-w-[24rem] gap-x-1"
            :style="{
                gridTemplateColumns: '3rem repeat(5, auto minmax(0, 1fr))',
                gridAutoRows: 'minmax(1.9rem, auto)',
            }"
        >
            <!-- Today's column: a full-height tint behind the cells -->
            <template v-for="(col, j) in columns" :key="'today' + j">
                <div
                    v-if="col.is_today"
                    class="pointer-events-none rounded-lg bg-hort-teal/10"
                    :style="{ gridColumn: `${bandCol(j)} / ${bandCol(j) + 2}`, gridRow: fullColumn }"
                />
            </template>

            <!-- Faint horizontal lines between the time slots -->
            <template v-for="(row, i) in rows" :key="'hr' + i">
                <div
                    v-if="i > 0"
                    class="pointer-events-none border-t border-ink/10"
                    :style="{ gridColumn: '1 / -1', gridRow: i + 2 }"
                />
            </template>

            <!-- Day headers (+ lunch/activity, which have no time span) -->
            <div
                v-for="(col, j) in columns"
                :key="'h' + j"
                class="px-0.5 pb-1 text-center"
                :class="col.is_today ? 'border-b-2 border-hort-teal' : 'border-b border-ink/10'"
                :style="{ gridRow: 1, gridColumn: `${bandCol(j)} / ${bandCol(j) + 2}` }"
            >
                <!-- The header links to that day's board (Heute). -->
                <component
                    :is="col.date ? Link : 'div'"
                    :href="col.date ? board({ query: { date: col.date } }).url : undefined"
                    :data-testid="col.date ? `wp-day-link-${col.date}` : undefined"
                    class="block rounded transition"
                    :class="col.date ? 'hover:bg-hort-teal/10' : ''"
                >
                    <div
                        class="text-xs font-semibold"
                        :class="col.is_today ? 'text-hort-teal-dark' : 'text-ink/50'"
                    >
                        {{ col.label }}<span v-if="col.is_today"> · {{ $t('common.today') }}</span>
                    </div>
                    <div
                        v-if="col.sublabel"
                        class="text-[11px]"
                        :class="col.is_today ? 'font-semibold text-hort-teal-dark' : 'text-ink/30'"
                    >
                        {{ col.sublabel }}
                    </div>
                </component>
                <div
                    v-if="program[j] && program[j].lunch"
                    class="mt-0.5 truncate text-[11px] text-ink/70"
                    :title="program[j].lunch"
                >
                    🍽 {{ program[j].lunch }}
                </div>
                <div
                    v-if="program[j] && program[j].activity"
                    class="truncate text-[11px] text-hort-purple"
                    :title="program[j].activity"
                >
                    🎨 {{ program[j].activity }}
                </div>
            </div>

            <!-- Time labels -->
            <div
                v-for="(row, i) in rows"
                :key="'t' + row.time"
                class="pr-1 pt-1 text-right text-xs font-medium tabular-nums text-ink/40"
                :style="{ gridColumn: 1, gridRow: i + 2 }"
            >
                {{ row.time }}
            </div>

            <!-- Ausflug bands (merged over their time range) -->
            <template v-for="(day, j) in activities" :key="'exday' + j">
                <div
                    v-for="(ex, k) in day || []"
                    v-show="bandRow(ex.depart_at, ex.return_at)"
                    :key="'ex' + j + '-' + k"
                    class="my-0.5 flex flex-col items-center gap-1 rounded-md bg-hort-purple/20 px-0.5 py-1 text-hort-purple"
                    :style="{
                        gridColumn: `${bandCol(j)} / ${bandCol(j) + 1}`,
                        gridRow: bandRow(ex.depart_at, ex.return_at) || 'auto',
                    }"
                    :title="ex.name + (ex.depart_at ? ` (${ex.depart_at}–${ex.return_at})` : '')"
                >
                    <span class="text-xs leading-none">🚌</span>
                    <span class="text-[9px] font-semibold [writing-mode:vertical-rl]">
                        {{ ex.name }}
                    </span>
                </div>
            </template>

            <!-- Hausaufgaben bands -->
            <template v-for="(p, j) in program" :key="'hwday' + j">
                <div
                    v-if="p && p.homework_start"
                    class="my-0.5 flex flex-col items-center justify-start rounded-md bg-amber-100 px-0.5 py-1 text-amber-700"
                    :style="{
                        gridColumn: `${bandCol(j)} / ${bandCol(j) + 1}`,
                        gridRow: bandRow(p.homework_start, p.homework_end) || 'auto',
                    }"
                    :title="$t('components.timetable.homework_range', { start: p.homework_start, end: p.homework_end || '' })"
                >
                    <span class="text-xs leading-none">📚</span>
                </div>
            </template>

            <!-- Pickups, beside the bands -->
            <template v-for="(row, i) in rows" :key="'r' + row.time">
                <template v-for="(kids, j) in row.days" :key="'k' + i + '-' + j">
                    <div
                        v-if="kids.length"
                        class="flex flex-col gap-1 py-0.5"
                        :style="{ gridColumn: `${kidsCol(j)} / ${kidsCol(j) + 1}`, gridRow: i + 2 }"
                    >
                        <component
                            :is="editable && kid.editable ? 'button' : 'div'"
                            v-for="kid in kids"
                            :key="kid.id"
                            type="button"
                            class="w-full rounded-md px-1.5 py-1 text-center text-[13px] font-semibold leading-tight"
                            :class="[
                                chipClass(kid.method),
                                kid.adjusted ? 'ring-2 ring-amber-400' : '',
                                editable && kid.editable
                                    ? 'cursor-pointer hover:brightness-95 active:scale-[0.97]'
                                    : '',
                            ]"
                            :title="kid.comment || undefined"
                            @click="editable && kid.editable ? emit('edit', kid, columns[j]) : null"
                        >
                            <span class="block truncate">
                                <span v-if="kid.excursion">🚌&nbsp;</span><span v-if="kid.method === 'sent_home'">🚶&nbsp;</span>{{ kid.name }}<span v-if="kid.qualifier_prefix" class="font-normal opacity-70">&nbsp;· {{ kid.qualifier_prefix }} {{ kid.time }}</span><span v-if="kid.companion" class="font-normal opacity-70">&nbsp;· {{ $t('weekly.companion_with', { name: kid.companion.name }) }}</span>
                            </span>
                            <span
                                v-if="kid.comment"
                                class="block truncate text-[11px] font-normal opacity-70"
                            >
                                {{ kid.comment }}
                            </span>
                        </component>
                    </div>
                </template>
            </template>
        </div>
    </div>
</template>
