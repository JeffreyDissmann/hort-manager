<script setup>
import { ref, computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { DocumentTextIcon, ArrowTopRightOnSquareIcon, ArrowDownTrayIcon, XMarkIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { search as paperlessSearch, suggest as paperlessSuggest } from '@/routes/accounting/paperless';
import { show as paperlessShow, thumb as paperlessThumb, download as paperlessDownload } from '@/routes/accounting/paperless/documents';

const props = defineProps({
    // The booking form (holds paperless_document_id + paperless_document_title).
    form: { type: Object, required: true },
    // Base URL of the Paperless instance for the „öffnen" deep link (null = disabled).
    paperlessUrl: { type: String, default: null },
    // Context for the AI suggestion (purpose / counterparty / amount / date).
    suggestContext: { type: Object, default: () => ({}) },
});

const query = ref('');
const results = ref([]);
const status = ref(''); // '', 'searching', 'no_results', 'not_found', 'ai_searching', 'ai_none'
let searchTimer = null;

const linkedId = computed(() => props.form.paperless_document_id);
const thumbUrl = (id) => paperlessThumb(id).url;
const downloadUrl = (id) => paperlessDownload(id).url;
const openUrl = (id) => (props.paperlessUrl ? `${props.paperlessUrl}/documents/${id}/` : null);

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

    if (raw === '') {
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

    status.value = 'searching';
    try {
        const { data } = await window.axios.get(paperlessSearch({ query: { q: raw } }).url);
        results.value = data.results ?? [];
        status.value = results.value.length ? '' : 'no_results';
    } catch {
        status.value = 'no_results';
    }
}

async function suggestWithAi() {
    status.value = 'ai_searching';
    results.value = [];
    try {
        const { data } = await window.axios.post(paperlessSuggest().url, props.suggestContext);
        if (data.best) {
            link(data.best);
        } else if (data.candidates?.length) {
            results.value = data.candidates;
            status.value = '';
        } else {
            status.value = 'ai_none';
        }
    } catch {
        status.value = 'ai_none';
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
                class="h-16 w-12 shrink-0 rounded border border-ink/10 bg-surface object-cover"
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

        <!-- Empty state: search / paste / AI -->
        <div v-else class="mt-1">
            <div class="flex gap-2">
                <TextInput
                    v-model="query"
                    type="text"
                    class="block w-full"
                    :placeholder="$t('accounting.paperless.search_placeholder')"
                    @input="onInput"
                />
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1 rounded-md border border-ink/20 px-3 text-sm font-medium text-ink/70 transition hover:bg-ink/5 disabled:opacity-50"
                    :disabled="status === 'ai_searching'"
                    @click="suggestWithAi"
                >
                    <SparklesIcon class="h-4 w-4" /> {{ $t('accounting.paperless.ai_suggest') }}
                </button>
            </div>

            <p v-if="status === 'searching'" class="mt-2 text-xs text-ink/50">{{ $t('accounting.paperless.searching') }}</p>
            <p v-else-if="status === 'ai_searching'" class="mt-2 text-xs text-ink/50">{{ $t('accounting.paperless.ai_searching') }}</p>
            <p v-else-if="status === 'no_results'" class="mt-2 text-xs text-ink/50">{{ $t('accounting.paperless.no_results') }}</p>
            <p v-else-if="status === 'not_found'" class="mt-2 text-xs text-red-600">{{ $t('accounting.paperless.not_found') }}</p>
            <p v-else-if="status === 'ai_none'" class="mt-2 text-xs text-ink/50">{{ $t('accounting.paperless.ai_none') }}</p>

            <!-- Result list -->
            <ul v-if="results.length" class="mt-2 divide-y divide-ink/5 overflow-hidden rounded-lg border border-ink/10">
                <li v-for="doc in results" :key="doc.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 p-2 text-left transition hover:bg-ink/5"
                        @click="link(doc)"
                    >
                        <img :src="thumbUrl(doc.id)" alt="" class="h-12 w-9 shrink-0 rounded border border-ink/10 bg-surface object-cover" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink">{{ doc.title || `#${doc.id}` }}</span>
                            <span v-if="doc.created" class="block text-xs text-ink/40">{{ doc.created }}</span>
                        </span>
                        <DocumentTextIcon class="h-4 w-4 shrink-0 text-ink/30" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
