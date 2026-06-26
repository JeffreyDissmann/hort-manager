<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    weekDays: { type: Array, default: () => [] },
    currentWeek: { type: Array, default: () => [] },
    standard: { type: Array, default: () => [] },
    methodOptions: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash?.status);
const standardWeekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

function cellClass(day) {
    if (!day.time) {
        return 'bg-hort-navy/5 text-hort-navy/30';
    }
    return day.method === 'sent_home'
        ? 'bg-hort-purple/15 text-hort-purple'
        : 'bg-hort-teal/20 text-hort-teal-dark';
}

function chipClass(method) {
    return method === 'sent_home'
        ? 'bg-hort-purple/15 text-hort-purple'
        : 'bg-hort-teal/25 text-hort-teal-dark';
}

// --- Inline editor (modal) ---
const editing = ref(null); // { childId, childName, date, label }
const form = reactive({ planned_time: '', planned_method: '' });

function openCell(child, day, dayMeta) {
    if (!day.editable) {
        return;
    }
    editing.value = {
        childId: child.id,
        childName: child.name,
        date: day.date,
        label: `${dayMeta.label} ${dayMeta.date_label}`,
    };
    form.planned_time = day.time ?? '';
    form.planned_method = day.method ?? '';
}

function closeEditor() {
    editing.value = null;
}

function save() {
    router.patch(
        route('weekly-plan.adjust'),
        {
            child_id: editing.value.childId,
            date: editing.value.date,
            planned_time: form.planned_time || null,
            planned_method: form.planned_method || null,
        },
        { preserveScroll: true, onSuccess: closeEditor },
    );
}

function resetDay() {
    router.patch(
        route('weekly-plan.reset'),
        { child_id: editing.value.childId, date: editing.value.date },
        { preserveScroll: true, onSuccess: closeEditor },
    );
}
</script>

<template>
    <Head title="Wochenplan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">Wochenplan</h2>
        </template>

        <div class="space-y-8">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <!-- Current week (effective plan, editable) -->
            <section class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-hort-navy/50">
                    Diese Woche
                </h3>

                <ul v-if="currentWeek.length" class="space-y-3">
                    <li
                        v-for="child in currentWeek"
                        :key="child.id"
                        class="rounded-2xl bg-white p-4 shadow-sm"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <p class="font-semibold text-hort-navy">
                                {{ child.name }}
                            </p>
                            <span
                                v-if="child.can_manage"
                                class="text-xs text-hort-navy/40"
                            >
                                Tippen zum Ändern
                            </span>
                        </div>

                        <div class="grid grid-cols-5 gap-1.5">
                            <div
                                v-for="(day, i) in child.days"
                                :key="day.date"
                                class="text-center"
                            >
                                <div class="text-[11px] font-medium text-hort-navy/40">
                                    {{ weekDays[i].label }}
                                </div>
                                <div class="text-[10px] text-hort-navy/30">
                                    {{ weekDays[i].date_label }}
                                </div>
                                <component
                                    :is="day.editable ? 'button' : 'div'"
                                    type="button"
                                    class="relative mt-1 w-full rounded-lg py-2 text-xs font-semibold"
                                    :class="[
                                        cellClass(day),
                                        day.adjusted ? 'ring-2 ring-amber-400' : '',
                                        day.editable ? 'cursor-pointer hover:brightness-95 active:scale-[0.97]' : '',
                                    ]"
                                    @click="openCell(child, day, weekDays[i])"
                                >
                                    {{ day.time ?? 'frei' }}
                                </component>
                            </div>
                        </div>
                    </li>
                </ul>

                <p
                    v-else
                    class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
                >
                    Noch keine Kinder angelegt.
                </p>

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-hort-navy/60">
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-hort-teal/60" />
                        wird abgeholt
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-hort-purple/50" />
                        geht allein
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full ring-2 ring-amber-400" />
                        geändert
                    </span>
                </div>
            </section>

            <!-- Standard Stammplan timetable -->
            <section class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-hort-navy/50">
                    Standard-Plan
                </h3>

                <div
                    v-if="standard.length"
                    class="overflow-x-auto rounded-2xl bg-white p-2 shadow-sm"
                >
                    <div class="min-w-[20rem]">
                        <div class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] gap-1 border-b border-hort-navy/10 pb-1">
                            <div></div>
                            <div
                                v-for="day in standardWeekdays"
                                :key="day"
                                class="py-1 text-center text-xs font-semibold text-hort-navy/50"
                            >
                                {{ day }}
                            </div>
                        </div>

                        <div
                            v-for="row in standard"
                            :key="row.time"
                            class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))] items-stretch gap-1 border-b border-hort-navy/5 last:border-0"
                        >
                            <div class="flex items-start justify-end pr-1 pt-1.5 text-[11px] font-medium tabular-nums text-hort-navy/40">
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
            </section>
        </div>

        <!-- Day editor -->
        <Modal :show="editing !== null" max-width="sm" @close="closeEditor">
            <div v-if="editing" class="space-y-5 p-6">
                <div>
                    <h2 class="text-lg font-semibold text-hort-navy">
                        {{ editing.childName }}
                    </h2>
                    <p class="text-sm text-hort-navy/50">
                        {{ editing.label }} – nur für diese Woche
                    </p>
                </div>

                <div>
                    <InputLabel for="time" value="Uhrzeit (leer = kommt nicht)" />
                    <TextInput
                        id="time"
                        v-model="form.planned_time"
                        type="time"
                        class="mt-1 block w-full"
                    />
                </div>

                <div>
                    <InputLabel for="method" value="Art" />
                    <select
                        id="method"
                        v-model="form.planned_method"
                        :disabled="!form.planned_time"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-hort-teal focus:ring-hort-teal disabled:bg-gray-100 disabled:text-gray-400"
                    >
                        <option value="">— offen —</option>
                        <option
                            v-for="o in methodOptions"
                            :key="o.value"
                            :value="o.value"
                        >
                            {{ o.label }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <button
                        type="button"
                        class="text-sm font-medium text-hort-navy/50 underline-offset-2 hover:underline"
                        @click="resetDay"
                    >
                        Auf Standard
                    </button>
                    <div class="flex gap-3">
                        <SecondaryButton @click="closeEditor">
                            Abbrechen
                        </SecondaryButton>
                        <PrimaryButton @click="save">Speichern</PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
