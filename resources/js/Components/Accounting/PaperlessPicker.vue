<script setup>
import { ref, computed, onMounted } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { DocumentTextIcon, ArrowTopRightOnSquareIcon, ArrowDownTrayIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { search as paperlessSearch } from '@/routes/accounting/paperless';
import { show as paperlessShow, thumb as paperlessThumb, download as paperlessDownload } from '@/routes/accounting/paperless/documents';
import { formatEuro } from '@/money';

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
let searchTimer = null;

const linkedId = computed(() => props.form.paperless_document_id);
const thumbUrl = (id) => paperlessThumb(id).url;
const downloadUrl = (id) => paperlessDownload(id).url;
const openUrl = (id) => (props.paperlessUrl ? `${props.paperlessUrl}/documents/${id}/` : null);

const hasSuggestionSignal = computed(() => props.initialQuery.trim() !== '' || !!props.amount || props.nearDate !== '');

// On open, offer the documents most similar to this booking (unlinked only — the
// endpoint hides linked ones). No AI, no waiting; the user picks or refines by typing.
onMounted(() => {
    if (!linkedId.value && hasSuggestionSignal.value) {
        fetchResults(props.initialQuery, { limit: 5, suggestions: true });
    }
});

function link(document) {
    props.form.paperless_document_id = document.id;
    props.form.paperless_document_title = document.title;
    query.value = '';
    results.value = [];
    status.value = '';
}

function unlink() {
    props.form.paperless_document_id = null;
    props.form.paperless_document_title = null;
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
    const query = { q, limit };
    // Suggestions add the booking's amount + valuta date as strong ranking signals.
    if (suggestions) {
        if (props.amount) {
            query.amount = props.amount;
        }
        if (props.nearDate) {
            query.near = props.nearDate;
        }
    }
    try {
        const { data } = await window.axios.get(paperlessSearch({ query }).url);
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
                <p class="truncate text-sm font-medium text-ink">{{ form.paperless_document_title || `#${linkedId}` }}</p>
                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs">
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
