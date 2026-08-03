<?php

declare(strict_types=1);

use App\Jobs\SyncPaperlessBookingLink;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\User;
use App\Services\Accounting\PaperlessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
    config()->set('services.paperless.booking_field', 4);
    config()->set('app.url', 'https://hort.test');
});

it('writes the booking deep-link into the current document', function () {
    Http::fake([
        'paperless.test/api/documents/57/' => Http::sequence()
            ->push(['id' => 57, 'custom_fields' => []])
            ->push(['id' => 57]),
    ]);

    $booking = Booking::factory()->create(['paperless_document_id' => 57]);
    $expectedUrl = route('accounting.bookings.edit', $booking->id);

    (new SyncPaperlessBookingLink($booking->id))->handle(app(PaperlessService::class));

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request->url() === 'https://paperless.test/api/documents/57/'
        && $request['custom_fields'] === [['field' => 4, 'value' => $expectedUrl]]);
});

it('clears the field on the previous document when a link moves', function () {
    Http::fake([
        'paperless.test/api/documents/40/' => Http::sequence()
            ->push(['id' => 40, 'custom_fields' => [['field' => 4, 'value' => 'old']]])
            ->push(['id' => 40]),
        'paperless.test/api/documents/57/' => Http::sequence()
            ->push(['id' => 57, 'custom_fields' => []])
            ->push(['id' => 57]),
    ]);

    $booking = Booking::factory()->create(['paperless_document_id' => 57]);

    (new SyncPaperlessBookingLink($booking->id, previousDocumentId: 40))->handle(app(PaperlessService::class));

    // Previous document 40 gets cleared…
    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && $r->url() === 'https://paperless.test/api/documents/40/'
        && $r['custom_fields'] === []);
    // …and 57 receives the link.
    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && $r->url() === 'https://paperless.test/api/documents/57/');
});

it('is a no-op when no custom field is configured', function () {
    config()->set('services.paperless.booking_field', null);
    Http::preventStrayRequests();

    $booking = Booking::factory()->create(['paperless_document_id' => 57]);

    (new SyncPaperlessBookingLink($booking->id))->handle(app(PaperlessService::class));

    Http::assertNothingSent();
});

it('dispatches a sync when a booking is saved with a document', function () {
    Queue::fake();
    $admin = User::factory()->admin()->accountingWriter()->create();
    $account = Account::factory()->create();
    $category = Category::factory()->expense()->create();

    $this->actingAs($admin)->post('/accounting/bookings', [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '19.95',
        'booking_date' => '2026-03-31',
        'paperless_document_id' => 57,
        'paperless_document_title' => 'Kassenbon',
    ])->assertRedirect();

    Queue::assertPushed(SyncPaperlessBookingLink::class);
});

it('dispatches a sync with the previous document when the link changes on edit', function () {
    Queue::fake();
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $booking = Booking::factory()->expense()->create(['paperless_document_id' => 40]);
    $category = Category::factory()->expense()->create();

    $this->put("/accounting/bookings/{$booking->id}", [
        'account_id' => $booking->account_id,
        'category_id' => $category->id,
        'amount' => '19.95',
        'booking_date' => '2026-03-31',
        'paperless_document_id' => 57,
        'paperless_document_title' => 'Kassenbon',
    ])->assertRedirect();

    Queue::assertPushed(SyncPaperlessBookingLink::class, fn ($job) => $job->bookingId === $booking->id && $job->previousDocumentId === 40);
});

it('keeps an auto-matched receipt when confirming during review', function () {
    Queue::fake();
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $category = Category::factory()->income()->create();
    // A suggested draft the import already linked to a receipt.
    $draft = Booking::factory()->suggested()->create([
        'amount_cents' => 5000,
        'category_id' => null,
        'paperless_document_id' => 57,
        'paperless_document_title' => 'Kassenbon',
    ]);

    $this->patch("/accounting/bookings/{$draft->id}/review", [
        'action' => 'confirm',
        'account_id' => $draft->account_id,
        'category_id' => $category->id,
        'amount' => '50',
        'booking_date' => '2026-04-01',
        'paperless_document_id' => 57,
        'paperless_document_title' => 'Kassenbon',
        'to_account_id' => null,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($draft->refresh()->paperless_document_id)->toBe(57);
});

it('dispatches a clear-sync when the link is removed on edit', function () {
    Queue::fake();
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $booking = Booking::factory()->expense()->create(['paperless_document_id' => 57, 'paperless_document_title' => 'Kassenbon']);
    $category = Category::factory()->expense()->create();

    $this->put("/accounting/bookings/{$booking->id}", [
        'account_id' => $booking->account_id,
        'category_id' => $category->id,
        'amount' => '19.95',
        'booking_date' => '2026-03-31',
        'paperless_document_id' => null,
    ])->assertRedirect();

    // The DB link is cleared…
    expect($booking->refresh()->paperless_document_id)->toBeNull();
    // …and a sync fires to clear document 57 in Paperless.
    Queue::assertPushed(SyncPaperlessBookingLink::class, fn ($job) => $job->bookingId === $booking->id && $job->previousDocumentId === 57);
});

it('does not dispatch a sync when the link is unchanged on edit', function () {
    Queue::fake();
    $admin = User::factory()->admin()->accountingWriter()->create();
    $this->actingAs($admin);
    $booking = Booking::factory()->expense()->create(['paperless_document_id' => 57, 'paperless_document_title' => 'Kassenbon']);
    $category = Category::factory()->expense()->create();

    $this->put("/accounting/bookings/{$booking->id}", [
        'account_id' => $booking->account_id,
        'category_id' => $category->id,
        'amount' => '20.00',
        'booking_date' => '2026-03-31',
        'paperless_document_id' => 57,
        'paperless_document_title' => 'Kassenbon',
    ])->assertRedirect();

    Queue::assertNotPushed(SyncPaperlessBookingLink::class);
});
