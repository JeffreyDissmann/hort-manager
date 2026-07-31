<?php

declare(strict_types=1);

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Jobs\SyncPaperlessBookingLink;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\User;
use App\Services\Accounting\PaperlessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
    config()->set('services.paperless.booking_field', 4);
    config()->set('services.paperless.amount_field', 1);
    $this->admin = User::factory()->admin()->accountingWriter()->create();
});

/** Fake a single reviewable document (id 8, 12,00 €, dated 2026-01-07) + empty correspondents. */
function fakeReviewDocuments(): void
{
    Http::fake([
        'paperless.test/api/correspondents*' => Http::response(['results' => []]),
        'paperless.test/api/documents*' => Http::response(['results' => [
            ['id' => 8, 'title' => 'Kassenbon', 'created' => '2026-01-07', 'custom_fields' => [['field' => 1, 'value' => 'EUR12.00']]],
        ]]),
    ]);
}

it('gates the wizard behind finishing the review', function () {
    Booking::factory()->suggested()->create();
    Http::preventStrayRequests();

    $this->actingAs($this->admin)
        ->get('/accounting/paperless/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Paperless/Review')
            ->where('gate.count', 1)
            ->where('documents', []));
});

it('lists unlinked documents with matching-booking candidates', function () {
    fakeReviewDocuments();
    // A confirmed, unlinked expense of 12,00 € near the document date → a candidate.
    $booking = Booking::factory()->create(['amount_cents' => -1200, 'booking_date' => '2026-01-08']);

    $this->actingAs($this->admin)
        ->get('/accounting/paperless/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('gate', null)
            ->where('documents.0.id', 8)
            ->where('documents.0.candidates.0.id', $booking->id));
});

it('does not offer transfers or already-linked bookings as candidates', function () {
    fakeReviewDocuments();
    Booking::factory()->create(['amount_cents' => -1200, 'paperless_document_id' => 99]); // linked
    Booking::factory()->create(['amount_cents' => -1200, 'kind' => BookingKind::Transfer]); // transfer

    $this->actingAs($this->admin)
        ->get('/accounting/paperless/review')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('documents.0.candidates', []));
});

it('attaches a document to a chosen booking', function () {
    Queue::fake();
    $booking = Booking::factory()->create(['paperless_document_id' => null]);

    $this->actingAs($this->admin)
        ->post('/accounting/paperless/attach', ['document_id' => 8, 'document_title' => 'Kassenbon', 'booking_id' => $booking->id])
        ->assertRedirect();

    expect($booking->refresh()->paperless_document_id)->toBe(8);
    Queue::assertPushed(SyncPaperlessBookingLink::class, fn ($job) => $job->bookingId === $booking->id);
});

it('will not attach a document already linked to another booking', function () {
    Booking::factory()->create(['paperless_document_id' => 8]); // 8 is taken
    $booking = Booking::factory()->create(['paperless_document_id' => null]);

    $this->actingAs($this->admin)
        ->post('/accounting/paperless/attach', ['document_id' => 8, 'booking_id' => $booking->id])
        ->assertStatus(422);

    expect($booking->refresh()->paperless_document_id)->toBeNull();
});

it('will not attach to a booking that is already linked', function () {
    $booking = Booking::factory()->create(['paperless_document_id' => 5]);

    $this->actingAs($this->admin)
        ->post('/accounting/paperless/attach', ['document_id' => 8, 'booking_id' => $booking->id])
        ->assertStatus(422);
});

it('creates a confirmed booking from a receipt and links it', function () {
    Queue::fake();
    $account = Account::factory()->create();
    $category = Category::factory()->expense()->create();

    $this->actingAs($this->admin)
        ->post('/accounting/paperless/bookings', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => '12.00',
            'booking_date' => '2026-01-07',
            'paperless_document_id' => 8,
            'paperless_document_title' => 'Kassenbon',
        ])->assertRedirect();

    $booking = Booking::where('paperless_document_id', 8)->first();
    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Confirmed);
    Queue::assertPushed(SyncPaperlessBookingLink::class);
});

it('ignores a document by writing the sentinel', function () {
    Http::fake(['paperless.test/api/documents/8/' => Http::sequence()
        ->push(['id' => 8, 'custom_fields' => []])
        ->push(['id' => 8])]);

    $this->actingAs($this->admin)
        ->post('/accounting/paperless/ignore', ['document_id' => 8])
        ->assertRedirect();

    $marker = app(PaperlessService::class)->ignoredMarker();
    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && $r['custom_fields'] === [['field' => 4, 'value' => $marker]]);
});
