<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const userName = computed(() => usePage().props.auth?.user?.name ?? '');
</script>

<template>
    <Head title="Übersicht" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-hort-navy">
                Hallo {{ userName }} 👋
            </h2>
        </template>

        <div class="space-y-4">
            <Link
                :href="route('children.index')"
                class="flex items-center justify-between rounded-2xl bg-white p-5 shadow-sm transition hover:shadow active:scale-[0.99]"
            >
                <div>
                    <h3 class="font-semibold text-hort-navy">Kinder & Stammplan</h3>
                    <p class="mt-1 text-sm text-hort-navy/60">
                        Kinder verwalten und Abholzeiten festlegen.
                    </p>
                </div>
                <span class="text-2xl text-hort-teal-dark">→</span>
            </Link>

            <div
                class="rounded-2xl border-2 border-dashed border-hort-navy/15 p-5 text-sm text-hort-navy/50"
            >
                Das Tagesboard (wer geht heute wann?) kommt als Nächstes.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
