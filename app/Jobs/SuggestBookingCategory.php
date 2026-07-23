<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Accounting\Booking;
use App\Services\Accounting\BookingSuggester;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Runs the AI category/counterparty suggestion for one imported draft. One job
 * per booking keeps the small local model reliable and isolates failures — a slow
 * or bad row can't wipe the rest of the import.
 *
 * A shared WithoutOverlapping lock guarantees only ONE Ollama call runs at a time
 * across all workers (the local model serves one request at a time); contended
 * jobs are released and retried until the review window closes.
 */
class SuggestBookingCategory implements ShouldQueue
{
    use Queueable;

    /** Must exceed the Ollama request timeout so a slow model doesn't kill the job. */
    public int $timeout = 45;

    /** A single unexpected exception shouldn't loop (the suggester swallows its own errors). */
    public int $maxExceptions = 1;

    public function __construct(public int $bookingId) {}

    /** Keep retrying on lock contention for a while; the work is idempotent. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // Serialize every suggestion job on one lock → one Ollama call at a time.
        return [(new WithoutOverlapping('ollama-suggest'))->releaseAfter(3)->expireAfter(120)];
    }

    public function handle(BookingSuggester $suggester): void
    {
        $booking = Booking::find($this->bookingId);

        if ($booking) {
            $suggester->suggest($booking);
        }
    }
}
