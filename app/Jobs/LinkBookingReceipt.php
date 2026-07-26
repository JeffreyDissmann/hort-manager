<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Accounting\Booking;
use App\Services\Accounting\PaperlessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deterministically link one unconfirmed booking to its archived receipt: the single
 * unlinked Paperless document whose amount equals the booking amount, near its valuta
 * date. No AI. Best-effort and idempotent — safe to re-run (the „Belege verknüpfen"
 * button). No-op for transfers, confirmed/already-linked bookings, or when nothing
 * certain matches (those are left for the user to link via the picker).
 */
class LinkBookingReceipt implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId) {}

    public function handle(PaperlessService $paperless): void
    {
        $booking = Booking::find($this->bookingId);

        if (
            ! $booking
            || $booking->paperless_document_id !== null
            || $booking->kind === BookingKind::Transfer
            || ! in_array($booking->status, [BookingStatus::Draft, BookingStatus::Suggested], true)
        ) {
            return;
        }

        $match = $paperless->confidentMatch(
            abs($booking->amount_cents) / 100,
            ($booking->valuta_date ?? $booking->booking_date)?->format('Y-m-d'),
            Booking::linkedDocumentIds(),
        );

        if ($match === null) {
            return;
        }

        // Only fills an empty link on a still-unconfirmed booking — never overwrites.
        $linked = Booking::whereKey($booking->id)
            ->whereIn('status', [BookingStatus::Draft, BookingStatus::Suggested])
            ->whereNull('paperless_document_id')
            ->update([
                'paperless_document_id' => $match['id'],
                'paperless_document_title' => $match['title'],
            ]);

        // Reverse-link into Paperless (query-builder update skips model events).
        if ($linked) {
            SyncPaperlessBookingLink::dispatch($booking->id);
        }
    }
}
