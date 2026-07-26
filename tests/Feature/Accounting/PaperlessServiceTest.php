<?php

declare(strict_types=1);

use App\Services\Accounting\PaperlessService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
});

it('is disabled without a url and token', function () {
    config()->set('services.paperless.url', null);

    Http::preventStrayRequests();

    $service = new PaperlessService;

    expect($service->enabled())->toBeFalse()
        ->and($service->search('rewe'))->toBe([])
        ->and($service->find(1))->toBeNull()
        ->and($service->baseUrl())->toBeNull();
});

it('maps full-text search results', function () {
    Http::fake([
        'paperless.test/api/documents/*' => Http::response([
            'results' => [
                ['id' => 12, 'title' => 'REWE Beleg', 'created' => '2026-05-01T00:00:00Z'],
                ['id' => 34, 'title' => 'Edeka', 'created' => '2026-05-02T00:00:00Z'],
            ],
        ]),
    ]);

    $results = (new PaperlessService)->search('rewe');

    expect($results)->toHaveCount(2)
        ->and($results[0])->toBe(['id' => 12, 'title' => 'REWE Beleg', 'created' => '2026-05-01T00:00:00Z']);
});

it('drops excluded (already-linked) documents from the results', function () {
    Http::fake([
        'paperless.test/api/documents/*' => Http::response([
            'results' => [
                ['id' => 12, 'title' => 'A', 'created' => null],
                ['id' => 34, 'title' => 'B', 'created' => null],
                ['id' => 56, 'title' => 'C', 'created' => null],
            ],
        ]),
    ]);

    $results = (new PaperlessService)->search('x', excludeIds: [34]);

    expect(collect($results)->pluck('id')->all())->toBe([12, 56]);
});

it('asks Paperless to omit documents that already have the booking field set', function () {
    config()->set('services.paperless.booking_field', 4);
    Http::fake(['paperless.test/api/documents*' => Http::response(['results' => []])]);

    (new PaperlessService)->search('rewe');

    Http::assertSent(fn ($request) => $request['custom_field_query'] === json_encode([4, 'exists', false]));
});

it('omits the custom-field filter when no booking field is configured', function () {
    config()->set('services.paperless.booking_field', null);
    Http::fake(['paperless.test/api/documents*' => Http::response(['results' => []])]);

    (new PaperlessService)->search('rewe');

    Http::assertSent(fn ($request) => ! isset($request['custom_field_query']));
});

it('sends the token as a Token header, not Bearer', function () {
    Http::fake(['paperless.test/api/*' => Http::response(['results' => []])]);

    (new PaperlessService)->search('rewe');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Token secret-token'));
});

it('resolves a single document by id', function () {
    Http::fake([
        'paperless.test/api/documents/12/' => Http::response(['id' => 12, 'title' => 'REWE Beleg', 'created' => '2026-05-01T00:00:00Z']),
    ]);

    expect((new PaperlessService)->find(12))
        ->toBe(['id' => 12, 'title' => 'REWE Beleg', 'created' => '2026-05-01T00:00:00Z']);
});

it('returns null for an unknown document', function () {
    Http::fake(['paperless.test/api/documents/99/' => Http::response(null, 404)]);

    expect((new PaperlessService)->find(99))->toBeNull();
});

it('exposes the base url for building deep links', function () {
    expect((new PaperlessService)->baseUrl())->toBe('https://paperless.test');
});

it('writes the booking deep-link into the configured custom field, keeping others', function () {
    config()->set('services.paperless.booking_field', 7);

    Http::fake([
        'paperless.test/api/documents/12/' => Http::sequence()
            ->push(['id' => 12, 'custom_fields' => [['field' => 3, 'value' => 'keep me'], ['field' => 7, 'value' => 'old link']]])
            ->push(['id' => 12]),
    ]);

    (new PaperlessService)->setBookingLink(12, 'https://hort.test/accounting/bookings/99/edit');

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['custom_fields'] === [
            ['field' => 3, 'value' => 'keep me'],
            ['field' => 7, 'value' => 'https://hort.test/accounting/bookings/99/edit'],
        ]);
});

it('clears the booking custom field when the url is null', function () {
    config()->set('services.paperless.booking_field', 7);

    Http::fake([
        'paperless.test/api/documents/12/' => Http::sequence()
            ->push(['id' => 12, 'custom_fields' => [['field' => 3, 'value' => 'keep me'], ['field' => 7, 'value' => 'old link']]])
            ->push(['id' => 12]),
    ]);

    (new PaperlessService)->setBookingLink(12, null);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['custom_fields'] === [['field' => 3, 'value' => 'keep me']]);
});

it('does not write back when no custom field is configured', function () {
    config()->set('services.paperless.booking_field', null);

    Http::preventStrayRequests();

    (new PaperlessService)->setBookingLink(12, 'https://hort.test/x');

    Http::assertNothingSent();
});
