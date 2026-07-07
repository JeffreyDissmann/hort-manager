<script setup>
defineProps({
    // Rows: [{ time, days: [ [ {id,name,method,comment,adjusted?,excursion?} ] × 5 ] }]
    rows: { type: Array, default: () => [] },
    // Column headers: [{ label, sublabel? }] × 5
    columns: { type: Array, default: () => [] },
    // Optional per-day program header: [{ lunch, activity, homework_start, homework_end }] × 5
    program: { type: Array, default: null },
    // Optional per-day excursions: [ [ {name, depart_at, return_at} ] ] × 5
    activities: { type: Array, default: null },
});

function chipClass(method) {
    return method === 'sent_home'
        ? 'bg-hort-purple/15 text-hort-purple'
        : 'bg-hort-teal/20 text-hort-teal-dark';
}
</script>

<template>
    <div class="overflow-x-auto rounded-2xl bg-surface p-2 shadow-sm">
        <div class="min-w-[22rem]">
            <!-- Day headers -->
            <div class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] gap-1 border-b border-ink/10 pb-1">
                <div></div>
                <div
                    v-for="(col, i) in columns"
                    :key="i"
                    class="py-1 text-center text-xs font-semibold text-ink/50"
                >
                    {{ col.label }}
                    <div v-if="col.sublabel" class="text-[10px] font-normal text-ink/30">
                        {{ col.sublabel }}
                    </div>
                </div>
            </div>

            <!-- Program header: lunch / activity / homework / excursions per day -->
            <div
                v-if="program"
                class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] gap-1 border-b border-ink/10 py-1"
            >
                <div></div>
                <div
                    v-for="(p, i) in program"
                    :key="i"
                    class="space-y-0.5 px-0.5 py-1 text-center"
                >
                    <div
                        v-if="p && p.lunch"
                        class="text-[10px] leading-tight text-ink/80"
                        :title="p.lunch"
                    >
                        🍽 {{ p.lunch }}
                    </div>
                    <div
                        v-if="p && p.activity"
                        class="text-[10px] leading-tight text-hort-purple"
                        :title="p.activity"
                    >
                        🎨 {{ p.activity }}
                    </div>
                    <div
                        v-if="p && p.homework_start"
                        class="text-[10px] font-medium leading-tight text-amber-700"
                    >
                        📚 {{ p.homework_start }}<span v-if="p.homework_end">–{{ p.homework_end }}</span>
                    </div>
                    <div
                        v-for="(a, j) in activities?.[i] ?? []"
                        :key="'ex-' + j"
                        class="rounded-md bg-hort-purple/15 px-1 py-0.5 text-[10px] font-semibold leading-tight text-hort-purple"
                        :title="a.name"
                    >
                        <span class="block truncate">🚌 {{ a.name }}</span>
                        <span v-if="a.depart_at" class="block font-normal opacity-80">
                            {{ a.depart_at }}–{{ a.return_at }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Time-slot rows -->
            <div
                v-for="row in rows"
                :key="row.time"
                class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] items-stretch gap-1 border-b border-ink/5 last:border-0"
            >
                <div class="flex items-start justify-end pr-1 pt-1.5 text-[11px] font-medium tabular-nums text-ink/40">
                    {{ row.time }}
                </div>
                <div v-for="(kids, i) in row.days" :key="i" class="space-y-1 py-1">
                    <div
                        v-for="kid in kids"
                        :key="kid.id"
                        class="rounded-md px-1.5 py-1 text-center text-[11px] font-semibold leading-tight"
                        :class="[chipClass(kid.method), kid.adjusted ? 'ring-2 ring-amber-400' : '']"
                        :title="kid.comment || undefined"
                    >
                        <span class="block truncate">
                            <span v-if="kid.excursion">🚌 </span>{{ kid.name }}
                        </span>
                        <span
                            v-if="kid.comment"
                            class="block truncate text-[9px] font-normal opacity-70"
                        >
                            {{ kid.comment }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
