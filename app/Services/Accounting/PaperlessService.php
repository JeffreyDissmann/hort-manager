<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single point of contact with the Paperless-ngx REST API. Every consumer
 * (the controller, the AI matcher, the import job) goes through here — no HTTP or
 * endpoint knowledge leaks out. Best-effort by design: when the integration is not
 * configured, or a request fails, methods return empty/null rather than throwing,
 * so the whole feature degrades silently (mirrors the Slack integration).
 */
class PaperlessService
{
    /** How many days around the reference (valuta) date a receipt may be dated. */
    private const NEAR_WINDOW_DAYS = 7;

    /** Memoised id → name map of Paperless correspondents (resolved once per instance). */
    private ?array $correspondentNames = null;

    /** Both the base URL and an API token must be set for the integration to work. */
    public function enabled(): bool
    {
        return filled(config('services.paperless.url')) && filled(config('services.paperless.token'));
    }

    /**
     * Full-text search. Returns a lean list of hits for the picker; pass
     * $withContent to also include an OCR snippet (used by the AI matcher to check
     * vendor/amount/date — Paperless already returns it, so this costs no extra call).
     * $excludeIds drops documents already linked to a booking so they aren't offered twice.
     * $withCorrespondent resolves the correspondent name for display in the picker.
     *
     * @param  list<int>  $excludeIds
     * @return list<array{id:int, title:string, created:?string, correspondent?:?string, content?:string}>
     */
    public function search(string $query, int $limit = 8, bool $withContent = false, array $excludeIds = [], bool $withCorrespondent = false): array
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return [];
        }

        return $this->request($this->unlinkedParams(['query' => $query]), $limit, $withContent, $withCorrespondent, $excludeIds);
    }

    /**
     * Find candidate receipts for a booking, strongest signal first: an exact amount
     * match (near-unique) then a full-text query narrowed to a date window around the
     * booking's (valuta) date. Both skip already-linked documents. Best-effort.
     *
     * @param  list<int>  $excludeIds
     * @return list<array{id:int, title:string, created:?string, correspondent?:?string, content?:string}>
     */
    public function candidatesFor(string $text, ?float $amount, ?string $nearDate, int $limit = 5, bool $withContent = false, bool $withCorrespondent = false, array $excludeIds = []): array
    {
        if (! $this->enabled()) {
            return [];
        }

        // 1. Exact amount match on the configured monetary field — the strongest signal.
        $results = [];
        $amountField = $this->amountFieldId();
        if ($amountField !== null && $amount !== null && $amount > 0) {
            $conditions = [[$amountField, 'exact', number_format($amount, 2, '.', '')]];
            if (($bookingField = $this->bookingFieldId()) !== null) {
                $conditions[] = [$bookingField, 'exists', false];
            }
            $query = count($conditions) > 1 ? ['AND', $conditions] : $conditions[0];
            $results = $this->request(['custom_field_query' => json_encode($query)], $limit, $withContent, $withCorrespondent, $excludeIds);
        }

        // 2. Full-text query within a date window around the reference date — the fallback.
        $text = trim($text);
        if ($text !== '' && count($results) < $limit) {
            $params = $this->unlinkedParams(['query' => $text]);
            if ($nearDate !== null && $nearDate !== '') {
                $reference = Carbon::parse($nearDate);
                $params['created__date__gte'] = $reference->copy()->subDays(self::NEAR_WINDOW_DAYS)->toDateString();
                $params['created__date__lte'] = $reference->copy()->addDays(self::NEAR_WINDOW_DAYS)->toDateString();
            }

            $seen = array_flip(array_column($results, 'id'));
            foreach ($this->request($params, $limit, $withContent, $withCorrespondent, $excludeIds) as $document) {
                if (! isset($seen[$document['id']])) {
                    $results[] = $document;
                }
            }
            $results = array_slice($results, 0, $limit);
        }

        return $results;
    }

    /**
     * Run one documents query and shape the results: map, drop already-linked ids, cap.
     *
     * @param  array<string, mixed>  $params
     * @param  list<int>  $excludeIds
     * @return list<array<string, mixed>>
     */
    private function request(array $params, int $limit, bool $withContent, bool $withCorrespondent, array $excludeIds): array
    {
        try {
            // Over-fetch so the excluded (already-linked) ones don't shrink the list.
            $params['page_size'] = min($limit + count($excludeIds), 100);
            $response = Http::paperless()->get('documents/', $params);

            if ($response->failed()) {
                return [];
            }

            $correspondents = $withCorrespondent ? $this->correspondentNames() : null;
            $exclude = array_flip($excludeIds);
            $results = array_values(array_filter(
                array_map(fn (array $d): array => $this->mapDocument($d, $withContent, $correspondents), $response->json('results', [])),
                fn (array $d): bool => ! isset($exclude[$d['id']]),
            ));

            return array_slice($results, 0, $limit);
        } catch (Throwable $e) {
            Log::warning('Paperless search failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Add the „not already linked to a booking" filter (custom field unset) to a query.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function unlinkedParams(array $params): array
    {
        if (($fieldId = $this->bookingFieldId()) !== null) {
            $params['custom_field_query'] = json_encode([$fieldId, 'exists', false]);
        }

        return $params;
    }

    /**
     * Resolve a single document by id (for the paste-id / paste-URL flow).
     *
     * @return array{id:int, title:string, created:?string}|null
     */
    public function find(int $id): ?array
    {
        if (! $this->enabled() || $id < 1) {
            return null;
        }

        try {
            $response = Http::paperless()->get("documents/{$id}/");

            if ($response->failed()) {
                return null;
            }

            return $this->mapDocument($response->json());
        } catch (Throwable $e) {
            Log::warning("Paperless lookup failed for #{$id}: ".$e->getMessage());

            return null;
        }
    }

    /** The document's thumbnail image, for streaming through the app. */
    public function thumbnail(int $id): ?Response
    {
        return $this->stream("documents/{$id}/thumb/");
    }

    /** The document's original file, for streaming through the app. */
    public function download(int $id): ?Response
    {
        return $this->stream("documents/{$id}/download/");
    }

    /** The Paperless base URL (no trailing slash) for building deep links, or null when disabled. */
    public function baseUrl(): ?string
    {
        return $this->enabled() ? rtrim((string) config('services.paperless.url'), '/') : null;
    }

    /**
     * Write (or clear) the booking deep-link on a document's configured custom field,
     * preserving its other custom fields. Pass null for $url to remove the link. No-op
     * when the integration or the custom field is not configured. Best-effort.
     */
    public function setBookingLink(int $documentId, ?string $url): void
    {
        $fieldId = $this->bookingFieldId();

        if (! $this->enabled() || $fieldId === null || $documentId < 1) {
            return;
        }

        try {
            $document = Http::paperless()->get("documents/{$documentId}/");

            if ($document->failed()) {
                return;
            }

            // Keep every other custom field; replace only our own entry.
            $fields = collect($document->json('custom_fields', []))
                ->reject(fn (array $field): bool => (int) ($field['field'] ?? 0) === $fieldId)
                ->values();

            if ($url !== null) {
                $fields->push(['field' => $fieldId, 'value' => $url]);
            }

            Http::paperless()->patch("documents/{$documentId}/", [
                'custom_fields' => $fields->all(),
            ]);
        } catch (Throwable $e) {
            Log::warning("Paperless booking-link write failed for #{$documentId}: ".$e->getMessage());
        }
    }

    /** The configured custom-field id for the booking deep-link, or null if unset. */
    public function bookingFieldId(): ?int
    {
        $field = config('services.paperless.booking_field');

        return filled($field) ? (int) $field : null;
    }

    /** The configured monetary custom-field id holding the document total, or null. */
    public function amountFieldId(): ?int
    {
        $field = config('services.paperless.amount_field');

        return filled($field) ? (int) $field : null;
    }

    /** Fetch a binary endpoint, returning the raw response for the caller to stream. */
    private function stream(string $path): ?Response
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::paperless()->get($path);

            return $response->successful() ? $response : null;
        } catch (Throwable $e) {
            Log::warning("Paperless fetch failed for {$path}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, string>|null  $correspondents  id → name map, or null to skip resolving
     * @return array{id:int, title:string, created:?string, correspondent?:?string, amount_cents?:?int, content?:string}
     */
    private function mapDocument(array $document, bool $withContent = false, ?array $correspondents = null): array
    {
        $mapped = [
            'id' => (int) ($document['id'] ?? 0),
            'title' => (string) ($document['title'] ?? ''),
            'created' => $document['created'] ?? null,
        ];

        if ($correspondents !== null) {
            $mapped['correspondent'] = $correspondents[$document['correspondent'] ?? null] ?? null;
        }

        // The document total from the monetary custom field (e.g. „EUR85.76" → 8576 cents).
        if (($amountField = $this->amountFieldId()) !== null) {
            $mapped['amount_cents'] = $this->documentAmountCents($document['custom_fields'] ?? [], $amountField);
        }

        // A trimmed OCR snippet — enough to carry the vendor, total and date.
        if ($withContent) {
            $mapped['content'] = mb_substr(trim((string) ($document['content'] ?? '')), 0, 600);
        }

        return $mapped;
    }

    /**
     * Parse the monetary custom field's value („EUR85.76", „85,76") into integer cents.
     *
     * @param  array<int, array{field?:int, value?:mixed}>  $customFields
     */
    private function documentAmountCents(array $customFields, int $fieldId): ?int
    {
        foreach ($customFields as $field) {
            if ((int) ($field['field'] ?? 0) !== $fieldId) {
                continue;
            }

            $raw = str_replace(',', '.', (string) ($field['value'] ?? ''));
            $number = preg_replace('/[^0-9.\-]/', '', $raw);

            return is_numeric($number) ? (int) round(((float) $number) * 100) : null;
        }

        return null;
    }

    /**
     * Best-effort id → name map of correspondents (for display). Empty when the API
     * token can't see them; resolved once and reused.
     *
     * @return array<int, string>
     */
    private function correspondentNames(): array
    {
        if ($this->correspondentNames !== null) {
            return $this->correspondentNames;
        }

        try {
            $response = Http::paperless()->get('correspondents/', ['page_size' => 500]);

            return $this->correspondentNames = $response->successful()
                ? collect($response->json('results', []))->pluck('name', 'id')->map(fn ($n): string => (string) $n)->all()
                : [];
        } catch (Throwable $e) {
            Log::warning('Paperless correspondents fetch failed: '.$e->getMessage());

            return $this->correspondentNames = [];
        }
    }
}
