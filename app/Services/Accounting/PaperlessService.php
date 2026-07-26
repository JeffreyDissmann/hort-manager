<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Booking;
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

    /** Memoised payment-type select options (resolved once per instance). */
    private ?array $paymentOptions = null;

    /** Both the base URL and an API token must be set for the integration to work. */
    public function enabled(): bool
    {
        return filled(config('services.paperless.url')) && filled(config('services.paperless.token'));
    }

    /**
     * Full-text search. Returns a lean list of hits for the picker; pass
     * $withContent to also include an OCR snippet (used by the AI matcher to check
     * vendor/amount/date — Paperless already returns it, so this costs no extra call).
     * $withCorrespondent resolves the correspondent name for display in the picker.
     * Already-linked documents are dropped (see request()).
     *
     * @return list<array{id:int, title:string, created:?string, correspondent?:?string, content?:string}>
     */
    public function search(string $query, int $limit = 8, bool $withContent = false, bool $withCorrespondent = false): array
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return [];
        }

        return $this->request($this->unlinkedParams(['query' => $query]), $limit, $withContent, $withCorrespondent);
    }

    /**
     * Find candidate receipts for a booking, strongest signal first: an exact amount
     * match (near-unique) then a full-text query narrowed to a date window around the
     * booking's (valuta) date. Both skip already-linked documents. Best-effort.
     *
     * @return list<array{id:int, title:string, created:?string, correspondent?:?string, content?:string}>
     */
    public function candidatesFor(string $text, ?float $amount, ?string $nearDate, int $limit = 5, bool $withContent = false, bool $withCorrespondent = false): array
    {
        if (! $this->enabled()) {
            return [];
        }

        // 1. Exact amount match on the configured monetary field — the strongest signal.
        $results = [];
        if ($amount !== null && $amount > 0 && ($amountQuery = $this->amountUnlinkedQuery($amount)) !== null) {
            $results = $this->request(['custom_field_query' => $amountQuery], $limit, $withContent, $withCorrespondent);
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
            foreach ($this->request($params, $limit, $withContent, $withCorrespondent) as $document) {
                if (! isset($seen[$document['id']])) {
                    $results[] = $document;
                }
            }
            $results = array_slice($results, 0, $limit);
        }

        return $results;
    }

    /**
     * The unlinked financial documents to walk in the „Belege zuordnen" wizard: not yet
     * linked (booking field empty — which also excludes ignored ones) AND carrying an
     * amount (the monetary field is set), created within the given range and optionally of
     * a given payment type. Requires the booking + amount fields; returns [] otherwise.
     *
     * @return list<array{id:int, title:string, created:?string, correspondent?:?string, amount_cents?:?int, payment?:?string}>
     */
    public function reviewCandidates(?string $from = null, ?string $to = null, ?string $payment = null, int $limit = 200): array
    {
        $bookingField = $this->bookingFieldId();
        $amountField = $this->amountFieldId();

        if (! $this->enabled() || $bookingField === null || $amountField === null) {
            return [];
        }

        $conditions = [[$bookingField, 'exists', false], [$amountField, 'exists', true]];
        if ($payment !== null && $payment !== '' && ($paymentField = $this->paymentFieldId()) !== null) {
            $conditions[] = [$paymentField, 'exact', $payment];
        }

        $params = [
            'custom_field_query' => json_encode(['AND', $conditions]),
            'ordering' => 'created',
        ];
        if ($from !== null && $from !== '') {
            $params['created__date__gte'] = Carbon::parse($from)->toDateString();
        }
        if ($to !== null && $to !== '') {
            $params['created__date__lte'] = Carbon::parse($to)->toDateString();
        }

        return $this->request($params, $limit, false, true);
    }

    /** Mark a document as „not a booking" by writing the ignore sentinel into the booking field. */
    public function ignore(int $documentId): void
    {
        $this->setBookingLink($documentId, $this->ignoredMarker());
    }

    /** The sentinel URL stored in the booking custom field to mark an ignored document. */
    public function ignoredMarker(): string
    {
        return url('/accounting/paperless/ignored');
    }

    /**
     * The one confident, deterministic auto-link candidate for a booking: the single
     * unlinked document whose amount custom field exactly equals the booking amount,
     * within the valuta date window. Null when there's no amount field, no match, or the
     * match is ambiguous (several same-amount documents) — those are left for the user.
     *
     * @return array{id:int, title:string, created:?string}|null
     */
    public function confidentMatch(?float $amount, ?string $nearDate): ?array
    {
        if (! $this->enabled() || $amount === null || $amount <= 0) {
            return null;
        }

        $amountQuery = $this->amountUnlinkedQuery($amount);
        if ($amountQuery === null) {
            return null;
        }

        $params = ['custom_field_query' => $amountQuery];
        if ($nearDate !== null && $nearDate !== '') {
            $reference = Carbon::parse($nearDate);
            $params['created__date__gte'] = $reference->copy()->subDays(self::NEAR_WINDOW_DAYS)->toDateString();
            $params['created__date__lte'] = $reference->copy()->addDays(self::NEAR_WINDOW_DAYS)->toDateString();
        }

        // Fetch two so an ambiguous (multiple same-amount) match can be detected and skipped.
        $results = $this->request($params, 2, false, false);

        return count($results) === 1 ? $results[0] : null;
    }

    /**
     * The custom_field_query for „amount equals $amount AND not already linked", or null
     * when no amount custom field is configured.
     */
    private function amountUnlinkedQuery(float $amount): ?string
    {
        $amountField = $this->amountFieldId();
        if ($amountField === null) {
            return null;
        }

        $conditions = [[$amountField, 'exact', number_format($amount, 2, '.', '')]];
        if (($bookingField = $this->bookingFieldId()) !== null) {
            $conditions[] = [$bookingField, 'exists', false];
        }

        return json_encode(count($conditions) > 1 ? ['AND', $conditions] : $conditions[0]);
    }

    /**
     * Run one documents query and shape the results: map, drop already-linked ids, cap.
     *
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function request(array $params, int $limit, bool $withContent, bool $withCorrespondent): array
    {
        try {
            // Over-fetch a little so documents filtered out below (linked in the write-back
            // lag window) don't shrink the page.
            $params['page_size'] = min($limit + 10, 250);
            $response = Http::paperless()->get('documents/', $params);

            if ($response->failed()) {
                return [];
            }

            $correspondents = $withCorrespondent ? $this->correspondentNames() : null;
            $documents = array_map(fn (array $d): array => $this->mapDocument($d, $withContent, $correspondents), $response->json('results', []));

            // Drop any already linked in our DB — a bounded check scoped to just this page's
            // ids (never loads the full linked set), a safety net for the brief window before
            // the Paperless write-back propagates / when the custom field isn't configured.
            $linked = $this->linkedAmong(array_column($documents, 'id'));
            $results = array_values(array_filter($documents, fn (array $d): bool => ! isset($linked[$d['id']])));

            return array_slice($results, 0, $limit);
        } catch (Throwable $e) {
            Log::warning('Paperless search failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Which of the given Paperless document ids are already linked to a booking — a bounded
     * whereIn (page-sized), so it scales no matter how many links exist overall.
     *
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private function linkedAmong(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_fill_keys(
            Booking::query()->whereIn('paperless_document_id', $ids)->pluck('paperless_document_id')->all(),
            true,
        );
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
     * Resolve a single document by id (paste-id / URL flow + the linked-state card),
     * including its correspondent and amount for display.
     *
     * @return array{id:int, title:string, created:?string, correspondent?:?string, amount_cents?:?int}|null
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

            return $this->mapDocument($response->json(), correspondents: $this->correspondentNames());
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

    /** The configured „select" custom-field id for the payment type, or null. */
    public function paymentFieldId(): ?int
    {
        $field = config('services.paperless.payment_field');

        return filled($field) ? (int) $field : null;
    }

    /**
     * The payment-type select options (id + label) for the wizard filter, resolved once
     * from the custom field definition. Empty when not configured.
     *
     * @return list<array{id:string, label:string}>
     */
    public function paymentOptions(): array
    {
        if ($this->paymentOptions !== null) {
            return $this->paymentOptions;
        }

        $fieldId = $this->paymentFieldId();
        if (! $this->enabled() || $fieldId === null) {
            return $this->paymentOptions = [];
        }

        try {
            $field = collect(Http::paperless()->get('custom_fields/', ['page_size' => 100])->json('results', []))
                ->firstWhere('id', $fieldId);

            return $this->paymentOptions = collect($field['extra_data']['select_options'] ?? [])
                ->map(fn (array $o): array => ['id' => (string) ($o['id'] ?? ''), 'label' => (string) ($o['label'] ?? '')])
                ->all();
        } catch (Throwable $e) {
            Log::warning('Paperless payment options fetch failed: '.$e->getMessage());

            return $this->paymentOptions = [];
        }
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

        // The payment type, resolved from the select field's option id to its label.
        if ($this->paymentFieldId() !== null) {
            $mapped['payment'] = $this->paymentLabel($document['custom_fields'] ?? []);
        }

        // A trimmed OCR snippet — enough to carry the vendor, total and date.
        if ($withContent) {
            $mapped['content'] = mb_substr(trim((string) ($document['content'] ?? '')), 0, 600);
        }

        return $mapped;
    }

    /**
     * Resolve the payment-type label from the select field's option id on a document.
     *
     * @param  array<int, array{field?:int, value?:mixed}>  $customFields
     */
    private function paymentLabel(array $customFields): ?string
    {
        $fieldId = $this->paymentFieldId();
        $optionId = null;
        foreach ($customFields as $field) {
            if ((int) ($field['field'] ?? 0) === $fieldId) {
                $optionId = $field['value'] ?? null;
                break;
            }
        }

        if ($optionId === null) {
            return null;
        }

        return collect($this->paymentOptions())->firstWhere('id', (string) $optionId)['label'] ?? null;
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
