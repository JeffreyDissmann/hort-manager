<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Jobs\LinkBookingReceipt;
use App\Jobs\SyncPaperlessBookingLink;
use App\Models\Accounting\Booking;
use App\Models\User;
use App\Services\Accounting\PaperlessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
    config()->set('services.paperless.booking_field', null);
    config()->set('services.paperless.amount_field', 1);
});

function runLink(int $bookingId): void
{
    (new LinkBookingReceipt($bookingId))->handle(app(PaperlessService::class));
}

it('links a unique exact-amount receipt to an unconfirmed booking', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response([
        'results' => [['id' => 4, 'title' => 'Kassenbon', 'created' => '2026-01-07', 'custom_fields' => [['field' => 1, 'value' => 'EUR85.76']]]],
    ])]);
    Queue::fake();

    $booking = Booking::factory()->draft()->create(['amount_cents' => -8576, 'booking_date' => '2026-01-09', 'valuta_date' => '2026-01-07']);

    runLink($booking->id);

    expect($booking->refresh())
        ->paperless_document_id->toBe(4)
        ->paperless_document_title->toBe('Kassenbon');
    Queue::assertPushed(SyncPaperlessBookingLink::class, fn ($job) => $job->bookingId === $booking->id);
});

it('does not link when several documents share the amount (ambiguous)', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response([
        'results' => [
            ['id' => 4, 'title' => 'A', 'created' => '2026-01-07', 'custom_fields' => [['field' => 1, 'value' => 'EUR85.76']]],
            ['id' => 5, 'title' => 'B', 'created' => '2026-01-08', 'custom_fields' => [['field' => 1, 'value' => 'EUR85.76']]],
        ],
    ])]);

    $booking = Booking::factory()->draft()->create(['amount_cents' => -8576, 'valuta_date' => '2026-01-07']);

    runLink($booking->id);

    expect($booking->refresh()->paperless_document_id)->toBeNull();
});

it('does not link a confirmed booking', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response([
        'results' => [['id' => 4, 'title' => 'Kassenbon', 'created' => '2026-01-07', 'custom_fields' => [['field' => 1, 'value' => 'EUR85.76']]]],
    ])]);

    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed, 'amount_cents' => -8576, 'valuta_date' => '2026-01-07']);

    runLink($booking->id);

    expect($booking->refresh()->paperless_document_id)->toBeNull();
});

it('does not link when no receipt matches the amount', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response(['results' => []])]);

    $booking = Booking::factory()->draft()->create(['amount_cents' => -8576, 'valuta_date' => '2026-01-07']);

    runLink($booking->id);

    expect($booking->refresh()->paperless_document_id)->toBeNull();
});

it('does nothing when Paperless is disabled', function () {
    config()->set('services.paperless.url', null);
    Http::preventStrayRequests();

    $booking = Booking::factory()->draft()->create(['amount_cents' => -8576, 'valuta_date' => '2026-01-07']);

    runLink($booking->id);

    expect($booking->refresh()->paperless_document_id)->toBeNull();
});

it('the relink button queues a receipt job per unconfirmed unlinked booking', function () {
    Queue::fake();
    $admin = User::factory()->admin()->accountingWriter()->create();

    $draft = Booking::factory()->draft()->create();
    $suggested = Booking::factory()->suggested()->create();
    Booking::factory()->create(); // confirmed — excluded
    Booking::factory()->draft()->create(['paperless_document_id' => 9]); // already linked — excluded

    $this->actingAs($admin)
        ->post('/accounting/bookings/relink-receipts')
        ->assertRedirect();

    Queue::assertPushed(LinkBookingReceipt::class, 2);
    Queue::assertPushed(LinkBookingReceipt::class, fn ($job) => $job->bookingId === $draft->id);
    Queue::assertPushed(LinkBookingReceipt::class, fn ($job) => $job->bookingId === $suggested->id);
});
