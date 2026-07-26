<?php

declare(strict_types=1);

use App\Services\Accounting\PaperlessService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
    // Don't inherit the developer's .env field ids — each test sets what it needs.
    config()->set('services.paperless.booking_field', null);
    config()->set('services.paperless.amount_field', null);
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

it('resolves the correspondent name when requested', function () {
    Http::fake([
        'paperless.test/api/correspondents*' => Http::response(['results' => [['id' => 23, 'name' => 'REWE Markt']]]),
        'paperless.test/api/documents*' => Http::response(['results' => [
            ['id' => 12, 'title' => 'Kassenbon', 'created' => '2026-03-31', 'correspondent' => 23],
            ['id' => 13, 'title' => 'Ohne', 'created' => '2026-03-30', 'correspondent' => null],
        ]]),
    ]);

    $results = (new PaperlessService)->search('rewe', withCorrespondent: true);

    expect($results[0]['correspondent'])->toBe('REWE Markt')
        ->and($results[1]['correspondent'])->toBeNull();
});

it('omits the correspondent field when not requested', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response(['results' => [
        ['id' => 12, 'title' => 'Kassenbon', 'created' => '2026-03-31', 'correspondent' => 23],
    ]])]);

    expect((new PaperlessService)->search('rewe')[0])->not->toHaveKey('correspondent');
});

it('ranks an exact amount match first, then text+date, deduped', function () {
    config()->set('services.paperless.amount_field', 1);

    Http::fake(['paperless.test/api/documents*' => Http::sequence()
        ->push(['results' => [['id' => 4, 'title' => 'Exact', 'created' => '2026-01-07']]])
        ->push(['results' => [['id' => 4, 'title' => 'Exact', 'created' => '2026-01-07'], ['id' => 7, 'title' => 'Text', 'created' => '2026-01-02']]]),
    ]);

    $results = (new PaperlessService)->candidatesFor('REWE', 85.76, '2026-01-07', limit: 5);

    expect(collect($results)->pluck('id')->all())->toBe([4, 7]);
    Http::assertSent(fn ($r) => ($r['custom_field_query'] ?? null) === json_encode([1, 'exact', '85.76']));
    Http::assertSent(fn ($r) => ($r['created__date__gte'] ?? null) === '2025-12-31' && ($r['created__date__lte'] ?? null) === '2026-01-14');
});

it('combines the amount and not-linked filters when both are configured', function () {
    config()->set('services.paperless.amount_field', 1);
    config()->set('services.paperless.booking_field', 4);
    Http::fake(['paperless.test/api/documents*' => Http::response(['results' => []])]);

    (new PaperlessService)->candidatesFor('REWE', 85.76, null, limit: 5);

    Http::assertSent(fn ($r) => ($r['custom_field_query'] ?? null) === json_encode(['AND', [[1, 'exact', '85.76'], [4, 'exists', false]]]));
});

it('skips the amount query when no amount field is configured', function () {
    Http::fake(['paperless.test/api/documents*' => Http::response(['results' => [['id' => 7, 'title' => 'T', 'created' => null]]])]);

    $results = (new PaperlessService)->candidatesFor('REWE', 85.76, '2026-01-07', limit: 5);

    expect(collect($results)->pluck('id')->all())->toBe([7]);
    Http::assertSentCount(1);
});

it('returns a confident match only for a unique exact-amount document', function () {
    config()->set('services.paperless.amount_field', 1);

    Http::fake(['paperless.test/api/documents*' => Http::response([
        'results' => [['id' => 4, 'title' => 'Kassenbon', 'created' => '2026-01-07']],
    ])]);

    $match = (new PaperlessService)->confidentMatch(85.76, '2026-01-07');

    expect($match)->toMatchArray(['id' => 4, 'title' => 'Kassenbon']);
    Http::assertSent(fn ($r) => ($r['custom_field_query'] ?? null) === json_encode([1, 'exact', '85.76'])
        && ($r['created__date__gte'] ?? null) === '2025-12-31');
});

it('returns no confident match when several documents share the amount', function () {
    config()->set('services.paperless.amount_field', 1);

    Http::fake(['paperless.test/api/documents*' => Http::response([
        'results' => [
            ['id' => 4, 'title' => 'A', 'created' => '2026-01-07'],
            ['id' => 5, 'title' => 'B', 'created' => '2026-01-08'],
        ],
    ])]);

    expect((new PaperlessService)->confidentMatch(85.76, '2026-01-07'))->toBeNull();
});

it('returns no confident match without an amount field', function () {
    Http::preventStrayRequests();

    expect((new PaperlessService)->confidentMatch(85.76, '2026-01-07'))->toBeNull();
});

it('extracts the document amount from the monetary custom field', function () {
    config()->set('services.paperless.amount_field', 1);
    Http::fake(['paperless.test/api/documents*' => Http::response(['results' => [
        ['id' => 4, 'title' => 'Kassenbon', 'created' => '2026-01-07', 'custom_fields' => [['field' => 1, 'value' => 'EUR85.76']]],
        ['id' => 5, 'title' => 'Ohne Betrag', 'created' => '2026-01-08', 'custom_fields' => []],
    ]])]);

    $results = (new PaperlessService)->search('rewe');

    expect($results[0]['amount_cents'])->toBe(8576)
        ->and($results[1]['amount_cents'])->toBeNull();
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
