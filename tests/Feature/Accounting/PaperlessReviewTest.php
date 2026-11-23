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
        'paperless.test/api/document_types*' => Http::response(['results' => [['id' => 4, 'name' => 'Rechnung']]]),
        'paperless.test/api/tags*' => Http::response(['results' => [['id' => 1, 'name' => 'Lebensmittel']]]),
        'paperless.test/api/documents*' => Http::response(['results' => [
            ['id' => 8, 'title' => 'Kassenbon', 'created' => '2026-01-07', 'document_type' => 4, 'tags' => [1], 'custom_fields' => [['field' => 1, 'value' => 'EUR12.00']]],
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
            ->where('documents.0.document_type', 'Rechnung')
            ->where('documents.0.tags', ['Lebensmittel'])
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

/**
 * Fake reviewable documents from [id, created, euros, correspondent id] rows; correspondent
 * 3 is „Bäckerei Huber".
 *
 * @param  list<array{0:int, 1:string, 2:string, 3?:int}>  $rows
 */
function fakeReviewDocumentRows(array $rows): void
{
    Http::fake([
        'paperless.test/api/correspondents*' => Http::response(['results' => [['id' => 3, 'name' => 'Bäckerei Huber']]]),
        'paperless.test/api/document_types*' => Http::response(['results' => []]),
        'paperless.test/api/tags*' => Http::response(['results' => []]),
        'paperless.test/api/documents*' => Http::response(['results' => array_map(fn (array $row): array => [
            'id' => $row[0],
            'title' => "Beleg {$row[0]}",
            'created' => $row[1],
            'correspondent' => $row[3] ?? null,
            'tags' => [],
            'custom_fields' => [['field' => 1, 'value' => "EUR{$row[2]}"]],
        ], $rows)]),
    ]);
}

it('offers bookings with a different amount around the document date as alternatives', function () {
    fakeReviewDocumentRows([[8, '2026-01-07', '12.00']]);
    $exact = Booking::factory()->create(['amount_cents' => -1200, 'booking_date' => '2026-01-08', 'valuta_date' => null]);
    $near = Booking::factory()->create(['amount_cents' => -1250, 'booking_date' => '2026-01-10', 'valuta_date' => null]);
    Booking::factory()->create(['amount_cents' => -1250, 'booking_date' => '2026-03-01', 'valuta_date' => null]); // too far off
    Booking::factory()->create(['amount_cents' => -1300, 'booking_date' => '2026-01-09', 'kind' => BookingKind::Transfer]);

    $this->actingAs($this->admin)
        ->get('/accounting/paperless/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('documents.0.candidates.0.id', $exact->id)
            ->has('documents.0.candidates', 1)
            ->where('documents.0.alternatives.0.id', $near->id)
            ->has('documents.0.alternatives', 1));
});

it('ranks an alternative naming the correspondent above a closer amount', function () {
    fakeReviewDocumentRows([[8, '2026-01-07', '12.00', 3]]);
    $closer = Booking::factory()->create(['amount_cents' => -1210, 'booking_date' => '2026-01-07', 'valuta_date' => null, 'counterparty_name' => 'Drogerie']);
    $named = Booking::factory()->create(['amount_cents' => -1500, 'booking_date' => '2026-01-12', 'valuta_date' => null, 'counterparty_name' => 'BÄCKEREI HUBER GmbH']);

    $this->actingAs($this->admin)
        ->get('/accounting/paperless/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('documents.0.alternatives.0.id', $named->id)
            ->where('documents.0.alternatives.1.id', $closer->id));
});

it('puts documents with an exact match first, keeping the date order within each group', function () {
    fakeReviewDocumentRows([[1, '2026-01-02', '5.00'], [2, '2026-01-03', '7.00'], [3, '2026-01-04', '9.00']]);
    Booking::factory()->create(['amount_cents' => -700, 'booking_date' => '2026-01-03']);
    Booking::factory()->create(['amount_cents' => -900, 'booking_date' => '2026-01-04']);

    $this->actingAs($this->admin)
        ->get('/accounting/paperless/review')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('documents.0.id', 2)
            ->where('documents.1.id', 3)
            ->where('documents.2.id', 1));
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
