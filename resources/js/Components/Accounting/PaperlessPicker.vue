<script setup>
import { ref, computed, onMounted } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { DocumentTextIcon, ArrowTopRightOnSquareIcon, ArrowDownTrayIcon, XMarkIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { search as paperlessSearch } from '@/routes/accounting/paperless';
import { show as paperlessShow, thumb as paperlessThumb, download as paperlessDownload } from '@/routes/accounting/paperless/documents';
import { formatEuro } from '@/money';
import { t } from '@/i18n';

const props = defineProps({
    // The booking form (holds paperless_document_id + paperless_document_title).
    form: { type: Object, required: true },
    // Base URL of the Paperless instance for the „öffnen" deep link (null = disabled).
    paperlessUrl: { type: String, default: null },
    // Full-text query for the initial „similar documents" suggestions (counterparty + purpose).
    initialQuery: { type: String, default: '' },
    // Booking amount + (valuta) date — strong signals for the initial suggestions.
    amount: { type: [String, Number], default: null },
    nearDate: { type: String, default: '' },
});

const query = ref('');
const results = ref([]);
const status = ref(''); // '', 'searching', 'no_results', 'not_found'
const showingSuggestions = ref(false); // results are the booking's auto-suggestions, not a typed search
const linkedDoc = ref(null); // full details (correspondent, amount, date) of the linked document
let searchTimer = null;

const linkedId = computed(() => props.form.paperless_document_id);
const thumbUrl = (id) => paperlessThumb(id).url;
const downloadUrl = (id) => paperlessDownload(id).url;
const openUrl = (id) => (props.paperlessUrl ? `${props.paperlessUrl}/documents/${id}/` : null);

const hasSuggestionSignal = computed(() => props.initialQuery.trim() !== '' || !!props.amount || props.nearDate !== '');

// Booking amount (positive magnitude) in cents, for comparing against the linked receipt.
const bookingCents = computed(() => {
    const value = parseFloat(String(props.amount).replace(',', '.'));
    return Number.isFinite(value) ? Math.round(Math.abs(value) * 100) : null;
});

// Flag a linked receipt whose total doesn't match the booking — likely the wrong document.
const amountMismatch = computed(() =>
    linkedDoc.value?.amount_cents != null && bookingCents.value !== null && linkedDoc.value.amount_cents !== bookingCents.value,
);

onMounted(() => {
    if (linkedId.value) {
        // A pre-existing link → fetch its details (correspondent, amount) for the card.
        loadLinkedDoc(linkedId.value);
    } else if (hasSuggestionSignal.value) {
        // Otherwise offer the documents most similar to this booking (unlinked only).
        fetchResults(props.initialQuery, { limit: 5, suggestions: true });
    }
});

async function loadLinkedDoc(id) {
    try {
        const { data } = await window.axios.get(paperlessShow(id).url);
        linkedDoc.value = data;
    } catch {
        linkedDoc.value = null;
    }
}

function link(document) {
    props.form.paperless_document_id = document.id;
    props.form.paperless_document_title = document.title;
    linkedDoc.value = document;
    query.value = '';
    results.value = [];
    status.value = '';
}

function unlink() {
    if (!confirm(t('accounting.paperless.remove_confirm'))) {
        return;
    }
    props.form.paperless_document_id = null;
    props.form.paperless_document_title = null;
    linkedDoc.value = null;
}

// A pasted number or Paperless URL → resolve directly; anything else → full-text search.
function parseId(raw) {
    const value = raw.trim();
    if (/^\d+$/.test(value)) {
        return Number(value);
    }
    const match = value.match(/\/documents\/(\d+)/);
    return match ? Number(match[1]) : null;
}

function onInput() {
    clearTimeout(searchTimer);
    const raw = query.value.trim();
    results.value = [];
    status.value = '';
    showingSuggestions.value = false;

    if (raw === '') {
        // Back to the booking's suggestions when the box is cleared.
        if (hasSuggestionSignal.value) {
            fetchResults(props.initialQuery, { limit: 5, suggestions: true });
        }
        return;
    }

    searchTimer = setTimeout(() => run(raw), 300);
}

async function run(raw) {
    const id = parseId(raw);

    if (id !== null) {
        status.value = 'searching';
        try {
            const { data } = await window.axios.get(paperlessShow(id).url);
            link(data);
        } catch {
            status.value = 'not_found';
        }
        return;
    }

    fetchResults(raw, { limit: 8, suggestions: false });
}

async function fetchResults(q, { limit, suggestions }) {
    status.value = 'searching';
    const params = { q, limit };
    // Suggestions add the booking's amount + valuta date as strong ranking signals.
    if (suggestions) {
        if (props.amount) {
            params.amount = props.amount;
        }
        if (props.nearDate) {
            params.near = props.nearDate;
        }
    }
    try {
        const { data } = await window.axios.get(paperlessSearch({ query: params }).url);
        results.value = data.results ?? [];
        showingSuggestions.value = suggestions && results.value.length > 0;
        status.value = results.value.length ? '' : (suggestions ? '' : 'no_results');
    } catch {
        results.value = [];
        status.value = suggestions ? '' : 'no_results';
    }
}
</script>

<template>
    <div>
        <InputLabel :value="$t('accounting.paperless.label')" />

        <!-- Linked document -->
        <div v-if="linkedId" class="mt-1 flex items-center gap-3 rounded-lg border border-ink/10 bg-ink/5 p-3">
            <img
                :src="thumbUrl(linkedId)"
                alt=""
                class="h-24 w-16 shrink-0 rounded border border-ink/10 bg-surface object-cover object-top"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-ink">{{ (linkedDoc && linkedDoc.title) || form.paperless_document_title || `#${linkedId}` }}</p>
                <p v-if="linkedDoc && linkedDoc.correspondent" class="truncate text-sm text-ink/70">{{ linkedDoc.correspondent }}</p>
                <p v-if="linkedDoc && linkedDoc.created" class="text-xs font-medium text-ink/50">{{ linkedDoc.created }}</p>
                <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                    <a
                        v-if="openUrl(linkedId)"
                        :href="openUrl(linkedId)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 text-hort-teal-dark hover:underline"
                    >
                        <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" /> {{ $t('accounting.paperless.open') }}
                    </a>
                    <a
                        :href="downloadUrl(linkedId)"
                        class="inline-flex items-center gap-1 text-hort-teal-dark hover:underline"
                    >
                        <ArrowDownTrayIcon class="h-3.5 w-3.5" /> {{ $t('accounting.paperless.download') }}
                    </a>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-red-600 hover:underline"
                        @click="unlink"
                    >
                        <XMarkIcon class="h-3.5 w-3.5" /> {{ $t('accounting.paperless.remove') }}
                    </button>
                </div>
            </div>
            <div v-if="linkedDoc && linkedDoc.amount_cents != null" class="shrink-0 self-start pl-2 text-right">
                <span class="text-lg font-semibold tabular-nums" :class="amountMismatch ? 'text-amber-600' : 'text-ink'">
                    {{ formatEuro(linkedDoc.amount_cents) }}
                </span>
                <span v-if="amountMismatch" class="mt-0.5 flex items-center justify-end gap-1 text-xs font-medium text-amber-600">
                    <ExclamationTriangleIcon class="h-3.5 w-3.5" /> {{ $t('accounting.paperless.amount_mismatch') }}
                </span>
            </div>
        </div>

        <!-- Empty state: search / paste, with the booking's similar documents offered up front -->
        <div v-else class="mt-1">
            <TextInput
                v-model="query"
                type="text"
                class="block w-full"
                :placeholder="$t('accounting.paperless.search_placeholder')"
                @input="onInput"
            />

            <p v-if="status === 'searching'" class="mt-2 text-xs text-ink/50">{{ $t('accounting.paperless.searching') }}</p>
            <p v-else-if="status === 'no_results'" class="mt-2 text-xs text-ink/50">{{ $t('accounting.paperless.no_results') }}</p>
            <p v-else-if="status === 'not_found'" class="mt-2 text-xs text-red-600">{{ $t('accounting.paperless.not_found') }}</p>

            <p v-if="showingSuggestions" class="mt-2 text-xs font-medium text-ink/50">{{ $t('accounting.paperless.suggestions') }}</p>

            <!-- Result list -->
            <ul v-if="results.length" class="mt-1 divide-y divide-ink/5 overflow-hidden rounded-lg border border-ink/10">
                <li v-for="doc in results" :key="doc.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 p-2.5 text-left transition hover:bg-ink/5"
                        @click="link(doc)"
                    >
                        <img :src="thumbUrl(doc.id)" alt="" class="h-24 w-16 shrink-0 rounded border border-ink/10 bg-surface object-cover object-top" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-ink">{{ doc.title || `#${doc.id}` }}</span>
                            <span v-if="doc.correspondent" class="mt-0.5 block truncate text-sm text-ink/70">{{ doc.correspondent }}</span>
                            <span v-if="doc.created" class="mt-0.5 block text-xs font-medium text-ink/50">{{ doc.created }}</span>
                        </span>
                        <span v-if="doc.amount_cents != null" class="shrink-0 pl-2 text-base font-semibold tabular-nums text-ink">{{ formatEuro(doc.amount_cents) }}</span>
                        <DocumentTextIcon v-else class="h-4 w-4 shrink-0 text-ink/30" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
