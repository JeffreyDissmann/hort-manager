<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Accounting\Booking;
use App\Services\Accounting\PaperlessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes the two-way link back into Paperless: the booking's deep-link URL is stored in
 * the configured custom field on its document, and cleared from the previously-linked
 * document when a link moves or is removed. Best-effort (the service swallows failures)
 * and a no-op when Paperless or the custom field isn't configured. Dispatched from every
 * place a booking's document link can change (manual save + import auto-match).
 */
class SyncPaperlessBookingLink implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId, public ?int $previousDocumentId = null) {}

    public function handle(PaperlessService $paperless): void
    {
        if (! $paperless->enabled() || $paperless->bookingFieldId() === null) {
            return;
        }

        $currentId = Booking::find($this->bookingId)?->paperless_document_id;

        // Detach from a document we're no longer linked to.
        if ($this->previousDocumentId && $this->previousDocumentId !== $currentId) {
            $paperless->setBookingLink($this->previousDocumentId, null);
        }

        // Attach (or refresh) the link on the current document.
        if ($currentId) {
            $paperless->setBookingLink($currentId, route('accounting.bookings.edit', $this->bookingId));
        }
    }
}
