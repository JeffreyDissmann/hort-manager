<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Http\Client\Response;
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
     *
     * @param  list<int>  $excludeIds
     * @return list<array{id:int, title:string, created:?string, content?:string}>
     */
    public function search(string $query, int $limit = 8, bool $withContent = false, array $excludeIds = []): array
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return [];
        }

        try {
            $params = [
                'query' => $query,
                // Over-fetch so the excluded (already-linked) ones don't shrink the list.
                'page_size' => min($limit + count($excludeIds), 100),
            ];

            // Ask Paperless itself to omit documents that already carry a booking link
            // (the custom field is set) — covers links made by any tool, not just this app.
            $fieldId = $this->bookingFieldId();
            if ($fieldId !== null) {
                $params['custom_field_query'] = json_encode([$fieldId, 'exists', false]);
            }

            $response = Http::paperless()->get('documents/', $params);

            if ($response->failed()) {
                return [];
            }

            $exclude = array_flip($excludeIds);
            $results = array_values(array_filter(
                array_map(fn (array $d): array => $this->mapDocument($d, $withContent), $response->json('results', [])),
                fn (array $d): bool => ! isset($exclude[$d['id']]),
            ));

            return array_slice($results, 0, $limit);
        } catch (Throwable $e) {
            Log::warning('Paperless search failed: '.$e->getMessage());

            return [];
        }
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
     * @return array{id:int, title:string, created:?string, content?:string}
     */
    private function mapDocument(array $document, bool $withContent = false): array
    {
        $mapped = [
            'id' => (int) ($document['id'] ?? 0),
            'title' => (string) ($document['title'] ?? ''),
            'created' => $document['created'] ?? null,
        ];

        // A trimmed OCR snippet — enough to carry the vendor, total and date.
        if ($withContent) {
            $mapped['content'] = mb_substr(trim((string) ($document['content'] ?? '')), 0, 600);
        }

        return $mapped;
    }
}
