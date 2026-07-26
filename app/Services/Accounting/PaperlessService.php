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
     * Full-text search. Returns a lean list of hits for the picker/matcher.
     *
     * @return list<array{id:int, title:string, created:?string}>
     */
    public function search(string $query, int $limit = 8): array
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return [];
        }

        try {
            $response = Http::paperless()->get('documents/', [
                'query' => $query,
                'page_size' => $limit,
            ]);

            if ($response->failed()) {
                return [];
            }

            return array_map($this->mapDocument(...), $response->json('results', []));
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

    /** Deep link into the Paperless web UI (for users with direct access). */
    public function deepLink(int $id): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return rtrim((string) config('services.paperless.url'), '/')."/documents/{$id}/";
    }

    /**
     * Accept a bare document id or a Paperless URL (…/documents/123,
     * …/documents/123/details) and return the id, or null if unrecognisable.
     */
    public function parseInput(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        if (preg_match('#/documents/(\d+)#', $raw, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
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
     * @return array{id:int, title:string, created:?string}
     */
    private function mapDocument(array $document): array
    {
        return [
            'id' => (int) ($document['id'] ?? 0),
            'title' => (string) ($document['title'] ?? ''),
            'created' => $document['created'] ?? null,
        ];
    }
}
