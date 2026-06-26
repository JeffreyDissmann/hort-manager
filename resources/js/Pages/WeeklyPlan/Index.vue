<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
});

const weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

// Chip color by departure method.
function chipClass(method) {
    return method === 'sent_home'
        ? 'bg-hort-purple/15 text-hort-purple'
        : 'bg-hort-teal/25 text-hort-teal-dark';
}
</script>

<template>
    <Head title="Wochenplan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Wochenplan</h2>
        </template>

        <div class="space-y-4">
            <!-- Legend -->
            <div
                class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-hort-navy/60"
            >
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-hort-teal/60" />
                    wird abgeholt
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-hort-purple/50" />
                    geht allein
                </span>
            </div>

            <div
                v-if="rows.length"
                class="overflow-x-auto rounded-2xl bg-white p-2 shadow-sm"
            >
                <div class="min-w-[20rem]">
                    <!-- Header: weekday columns -->
                    <div
                        class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] gap-1 border-b border-hort-navy/10 pb-1"
                    >
                        <div></div>
                        <div
                            v-for="day in weekdays"
                            :key="day"
                            class="py-1 text-center text-xs font-semibold text-hort-navy/50"
                        >
                            {{ day }}
                        </div>
                    </div>

                    <!-- Time-slot rows -->
                    <div
                        v-for="row in rows"
                        :key="row.time"
                        class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] items-stretch gap-1 border-b border-hort-navy/5 last:border-0"
                    >
                        <div
                            class="flex items-start justify-end pr-1 pt-1.5 text-[11px] font-medium tabular-nums text-hort-navy/40"
                        >
                            {{ row.time }}
                        </div>

                        <div
                            v-for="(kids, i) in row.days"
                            :key="i"
                            class="space-y-1 py-1"
                        >
                            <div
                                v-for="kid in kids"
                                :key="kid.id"
                                class="truncate rounded-md px-1.5 py-1 text-center text-[11px] font-semibold leading-tight"
                                :class="chipClass(kid.method)"
                                :title="`${kid.name} · ${kid.time}`"
                            >
                                {{ kid.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
            >
                Noch keine Abholzeiten im Stammplan hinterlegt.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
