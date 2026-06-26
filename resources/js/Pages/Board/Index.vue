<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    date: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    canMark: { type: Boolean, default: false },
    methodOptions: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash?.status);

const methodLabels = computed(() =>
    Object.fromEntries(props.methodOptions.map((o) => [o.value, o.label])),
);

const counts = computed(() => {
    let present = 0;
    let left = 0;
    let excursion = 0;
    for (const r of props.rows) {
        if (r.status === 'present') present++;
        else if (r.status === 'excursion') excursion++;
        else left++;
    }
    return { present, left, excursion };
});

function planLabel(row) {
    const method = methodLabels.value[row.planned_method];
    return method ? `${row.planned_time} · ${method}` : row.planned_time;
}

function mark(row, status) {
    router.patch(
        route('board.mark', row.id),
        { status },
        { preserveScroll: true },
    );
}

// --- Same-day override (inline editor) ---
const editingId = ref(null);
const editTime = ref('');
const editMethod = ref('');

function openEdit(row) {
    editingId.value = row.id;
    editTime.value = row.planned_time ?? '';
    editMethod.value = row.planned_method ?? '';
}

function cancelEdit() {
    editingId.value = null;
}

function saveEdit(row) {
    router.patch(
        route('board.override', row.id),
        { planned_time: editTime.value, planned_method: editMethod.value || null },
        { preserveScroll: true, onSuccess: () => (editingId.value = null) },
    );
}
</script>

<template>
    <Head title="Tagesboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-hort-navy">
                    {{ date.is_today ? 'Heute' : 'Nächster Hort-Tag' }}
                </h2>
                <p class="text-sm text-hort-navy/50">{{ date.label }}</p>
            </div>
        </template>

        <div class="space-y-4">
            <div
                v-if="flash"
                class="rounded-2xl bg-hort-teal/20 px-4 py-3 text-sm font-medium text-hort-navy"
            >
                {{ flash }}
            </div>

            <!-- Summary -->
            <div v-if="rows.length" class="flex gap-2 text-sm font-semibold">
                <span class="rounded-xl bg-white px-3 py-2 text-hort-navy shadow-sm">
                    {{ counts.present }} noch da
                </span>
                <span class="rounded-xl bg-white px-3 py-2 text-hort-navy/50 shadow-sm">
                    {{ counts.left }} weg
                </span>
                <span
                    v-if="counts.excursion"
                    class="rounded-xl bg-white px-3 py-2 text-hort-purple shadow-sm"
                >
                    {{ counts.excursion }} Ausflug
                </span>
            </div>

            <ul v-if="rows.length" class="space-y-3">
                <li
                    v-for="row in rows"
                    :key="row.id"
                    class="rounded-2xl bg-white p-4 shadow-sm transition"
                    :class="{ 'opacity-60': row.status === 'picked_up' || row.status === 'sent_home' }"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-hort-navy">{{ row.name }}</p>
                            <p class="mt-0.5 text-sm text-hort-navy/60">
                                {{ planLabel(row) }}
                                <span
                                    v-if="row.is_overridden"
                                    class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-700"
                                >
                                    heute geändert
                                </span>
                            </p>
                        </div>

                        <!-- Status badge once the child has left -->
                        <div
                            v-if="row.status === 'picked_up' || row.status === 'sent_home'"
                            class="shrink-0 text-right"
                        >
                            <p
                                class="text-sm font-semibold"
                                :class="row.status === 'sent_home' ? 'text-hort-purple' : 'text-hort-teal-dark'"
                            >
                                ✓ {{ row.status_label }}
                            </p>
                            <p class="text-xs text-hort-navy/40">
                                {{ row.left_at }}<span v-if="row.marked_by"> · {{ row.marked_by }}</span>
                            </p>
                        </div>
                        <span
                            v-else-if="row.status === 'excursion'"
                            class="shrink-0 rounded-lg bg-hort-purple/15 px-2 py-1 text-xs font-semibold text-hort-purple"
                        >
                            Ausflug
                        </span>
                    </div>

                    <!-- Inline same-day override editor -->
                    <div
                        v-if="editingId === row.id"
                        class="mt-3 space-y-3 rounded-xl bg-hort-sand p-3"
                    >
                        <div class="flex gap-2">
                            <input
                                v-model="editTime"
                                type="time"
                                class="w-32 rounded-lg border-gray-300 text-sm focus:border-hort-teal focus:ring-hort-teal"
                            />
                            <select
                                v-model="editMethod"
                                class="flex-1 rounded-lg border-gray-300 text-sm focus:border-hort-teal focus:ring-hort-teal"
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
                        <div class="flex justify-end gap-3 text-sm">
                            <button
                                type="button"
                                class="px-2 py-1 text-hort-navy/60"
                                @click="cancelEdit"
                            >
                                Abbrechen
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-hort-navy px-4 py-1.5 font-semibold text-white"
                                @click="saveEdit(row)"
                            >
                                Speichern
                            </button>
                        </div>
                    </div>

                    <!-- Actions -->
                    <template v-else>
                        <div
                            v-if="row.status === 'present'"
                            class="mt-3 space-y-2"
                        >
                            <div v-if="canMark" class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl bg-hort-teal py-3 font-semibold text-hort-navy transition hover:bg-hort-teal-dark active:scale-[0.98]"
                                    @click="mark(row, 'picked_up')"
                                >
                                    Abgeholt
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl bg-hort-purple py-3 font-semibold text-white transition hover:opacity-90 active:scale-[0.98]"
                                    @click="mark(row, 'sent_home')"
                                >
                                    Nach Hause
                                </button>
                            </div>
                            <button
                                v-if="row.can_override"
                                type="button"
                                class="text-sm font-medium text-hort-navy/50 underline-offset-2 hover:underline"
                                @click="openEdit(row)"
                            >
                                Heute ändern
                            </button>
                        </div>

                        <div v-else-if="row.status !== 'excursion' && canMark" class="mt-3">
                            <button
                                type="button"
                                class="text-sm font-medium text-hort-navy/50 underline-offset-2 hover:underline"
                                @click="mark(row, 'present')"
                            >
                                Rückgängig
                            </button>
                        </div>
                    </template>
                </li>
            </ul>

            <p
                v-else
                class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-6 text-center text-sm text-hort-navy/50"
            >
                Für diesen Tag sind keine Kinder im Hort.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
