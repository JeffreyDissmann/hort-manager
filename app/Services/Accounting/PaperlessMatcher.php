<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Ai\Agents\ReceiptMatcher;
use App\Models\Accounting\Booking;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

/**
 * Finds the Paperless document that belongs to a booking. Full-text search (via the
 * booking's counterparty + purpose) produces a shortlist; the local Ollama model then
 * picks the single best match and rates its confidence. Best-effort: with no candidates,
 * AI disabled, or any failure it returns no „best" pick (the shortlist is still handed
 * back so a human can choose). Never throws to callers.
 */
class PaperlessMatcher
{
    public function __construct(private readonly PaperlessService $paperless) {}

    /**
     * Match against a saved booking (import auto-match path).
     *
     * @return array{best: array{id:int, title:string, created:?string, confidence:?string}|null, candidates: list<array{id:int, title:string, created:?string}>}
     */
    public function forBooking(Booking $booking): array
    {
        return $this->match([
            'purpose' => $booking->purpose,
            'counterparty' => $booking->counterpartyLabel(),
            'amount' => round(abs($booking->amount_cents) / 100, 2),
            'date' => $booking->booking_date?->format('Y-m-d'),
        ]);
    }

    /**
     * Match against loose booking fields (the create/edit form's „KI-Vorschlag" button).
     *
     * @param  array{purpose?:?string, counterparty?:?string, amount?:int|float|null, date?:?string}  $context
     * @return array{best: array{id:int, title:string, created:?string, confidence:?string}|null, candidates: list<array{id:int, title:string, created:?string}>}
     */
    public function match(array $context): array
    {
        $query = $this->query($context);
        // Ask for OCR content too — the model matches on vendor/amount/date, but the
        // caller only needs the lean fields, so strip content before returning. Skip
        // documents already linked to another booking.
        $rich = $query === '' ? [] : $this->paperless->search($query, withContent: true, excludeIds: Booking::linkedDocumentIds());
        $lean = array_map(fn (array $d): array => ['id' => $d['id'], 'title' => $d['title'], 'created' => $d['created']], $rich);

        return [
            'best' => $rich === [] ? null : $this->rank($context, $rich),
            'candidates' => $lean,
        ];
    }

    /** Build the full-text query from the most identifying fields. */
    private function query(array $context): string
    {
        return trim(collect([$context['counterparty'] ?? null, $context['purpose'] ?? null])
            ->filter()
            ->implode(' '));
    }

    /**
     * Ask Ollama to pick the best candidate. Returns the chosen document (enriched with
     * the model's confidence) or null when AI is off, nothing fits, or the call fails.
     *
     * @param  list<array{id:int, title:string, created:?string}>  $candidates
     * @return array{id:int, title:string, created:?string, confidence:?string}|null
     */
    private function rank(array $context, array $candidates): ?array
    {
        if (! config('accounting.ai_suggestions')) {
            return null;
        }

        try {
            $response = (new ReceiptMatcher($candidates))->prompt(
                json_encode($context, JSON_UNESCAPED_UNICODE),
                provider: Lab::Ollama,
                model: (string) config('ai.providers.ollama.model'),
            );
        } catch (Throwable $e) {
            Log::warning('Paperless AI match failed: '.$e->getMessage());

            return null;
        }

        $documentId = $response['document_id'] ?? null;

        if (! $documentId) {
            return null;
        }

        $match = collect($candidates)->firstWhere('id', (int) $documentId);

        return $match === null ? null : [
            'id' => $match['id'],
            'title' => $match['title'],
            'created' => $match['created'],
            'confidence' => $response['confidence'] ?? null,
        ];
    }
}
