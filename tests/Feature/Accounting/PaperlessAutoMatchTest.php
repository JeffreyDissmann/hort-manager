<?php

declare(strict_types=1);

use App\Ai\Agents\BookingCategorizer;
use App\Ai\Agents\ReceiptMatcher;
use App\Jobs\SuggestBookingCategory;
use App\Jobs\SyncPaperlessBookingLink;
use App\Models\Accounting\Booking;
use App\Services\Accounting\BookingSuggester;
use App\Services\Accounting\PaperlessMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
    config()->set('services.paperless.booking_field', null);
    config()->set('services.paperless.amount_field', null);
    config()->set('accounting.ai_suggestions', true);
    // The category suggester runs first; keep it a no-op so we isolate the receipt match.
    BookingCategorizer::fake([[]]);
});

it('auto-links a high-confidence receipt during the import AI pass', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response([
        'results' => [['id' => 57, 'title' => 'Kassenbon Blumenerde-Kauf', 'created' => '2026-03-31']],
    ])]);
    ReceiptMatcher::fake([['document_id' => 57, 'confidence' => 'high']]);

    $booking = Booking::factory()->draft()->create(['purpose' => 'REWE BLUMENERDE']);

    SuggestBookingCategory::dispatchSync($booking->id);

    expect($booking->refresh())
        ->paperless_document_id->toBe(57)
        ->paperless_document_title->toBe('Kassenbon Blumenerde-Kauf');
});

it('dispatches the Paperless write-back after an auto-link', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response([
        'results' => [['id' => 57, 'title' => 'Kassenbon Blumenerde-Kauf', 'created' => '2026-03-31']],
    ])]);
    ReceiptMatcher::fake([['document_id' => 57, 'confidence' => 'high']]);
    Queue::fake();

    $booking = Booking::factory()->draft()->create(['purpose' => 'REWE BLUMENERDE']);

    // Run the job body directly so Queue::fake only records the inner write-back dispatch.
    (new SuggestBookingCategory($booking->id))->handle(
        app(BookingSuggester::class),
        app(PaperlessMatcher::class),
    );

    expect($booking->refresh()->paperless_document_id)->toBe(57);
    Queue::assertPushed(SyncPaperlessBookingLink::class, fn ($job) => $job->bookingId === $booking->id);
});

it('does not auto-link a merely medium-confidence match', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response([
        'results' => [['id' => 57, 'title' => 'Kassenbon', 'created' => '2026-03-31']],
    ])]);
    ReceiptMatcher::fake([['document_id' => 57, 'confidence' => 'medium']]);

    $booking = Booking::factory()->draft()->create(['purpose' => 'unklar']);

    SuggestBookingCategory::dispatchSync($booking->id);

    expect($booking->refresh()->paperless_document_id)->toBeNull();
});

it('leaves the booking unlinked when Paperless is disabled', function () {
    config()->set('services.paperless.url', null);
    Http::preventStrayRequests();

    $booking = Booking::factory()->draft()->create(['purpose' => 'REWE']);

    SuggestBookingCategory::dispatchSync($booking->id);

    expect($booking->refresh()->paperless_document_id)->toBeNull();
});
