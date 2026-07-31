<?php

declare(strict_types=1);

use App\Models\Accounting\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
    config()->set('services.paperless.booking_field', null);
    config()->set('services.paperless.amount_field', null);
    $this->admin = User::factory()->admin()->accountingWriter()->create();
});

it('proxies a full-text search', function () {
    Http::fake([
        'paperless.test/api/documents*' => Http::response([
            'results' => [['id' => 12, 'title' => 'REWE Beleg', 'created' => '2026-05-01']],
        ]),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/accounting/paperless/search?q=rewe')
        ->assertOk()
        ->assertJson(['results' => [['id' => 12, 'title' => 'REWE Beleg']]]);
});

it('uses amount + date suggestions when a booking context is sent', function () {
    config()->set('services.paperless.amount_field', 1);

    Http::fake(['paperless.test/api/documents*' => Http::sequence()
        ->push(['results' => [['id' => 4, 'title' => 'Exact', 'created' => '2026-01-07']]])
        ->push(['results' => [['id' => 7, 'title' => 'Text', 'created' => '2026-01-02']]]),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/accounting/paperless/search?q=REWE&amount=85.76&near=2026-01-07')
        ->assertOk()
        ->assertJsonPath('results.0.id', 4);

    Http::assertSent(fn ($r) => ($r['custom_field_query'] ?? null) === json_encode([1, 'exact', '85.76']));
});

it('caps search results to the requested limit', function () {
    Http::fake([
        'paperless.test/api/documents*' => Http::response([
            'results' => [
                ['id' => 1, 'title' => 'A', 'created' => null],
                ['id' => 2, 'title' => 'B', 'created' => null],
                ['id' => 3, 'title' => 'C', 'created' => null],
            ],
        ]),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/accounting/paperless/search?q=x&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'results');
});

it('hides documents already linked to a booking from search', function () {
    Booking::factory()->create(['paperless_document_id' => 12]);

    Http::fake([
        'paperless.test/api/documents*' => Http::response([
            'results' => [
                ['id' => 12, 'title' => 'Already linked', 'created' => null],
                ['id' => 34, 'title' => 'Free', 'created' => null],
            ],
        ]),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/accounting/paperless/search?q=beleg')
        ->assertOk()
        ->assertJsonPath('results.0.id', 34)
        ->assertJsonCount(1, 'results');
});

it('resolves a document by id', function () {
    Http::fake([
        'paperless.test/api/documents/12/' => Http::response(['id' => 12, 'title' => 'REWE Beleg', 'created' => '2026-05-01']),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/accounting/paperless/documents/12')
        ->assertOk()
        ->assertJson(['id' => 12, 'title' => 'REWE Beleg']);
});

it('404s an unknown document', function () {
    Http::fake(['paperless.test/api/documents/99/' => Http::response(null, 404)]);

    $this->actingAs($this->admin)
        ->getJson('/accounting/paperless/documents/99')
        ->assertNotFound();
});

it('streams the thumbnail as image/webp with nosniff and no token', function () {
    Http::fake([
        'paperless.test/api/documents/12/thumb/' => Http::response('BINARYIMAGE', 200, ['Content-Type' => 'text/html']),
    ]);

    $response = $this->actingAs($this->admin)->get('/accounting/paperless/documents/12/thumb');

    // Forced to image/webp (never the upstream text/html) + nosniff → no inline HTML/SVG XSS.
    $response->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->content())->toBe('BINARYIMAGE')->not->toContain('secret-token');
});

it('forces a download as an attachment', function () {
    Http::fake(['paperless.test/api/documents/12/download/' => Http::response('FILE', 200, ['Content-Type' => 'text/html'])]);

    $this->actingAs($this->admin)
        ->get('/accounting/paperless/documents/12/download')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('Content-Disposition', 'attachment; filename="beleg-12"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('lets a read-only user see a linked thumbnail but not an arbitrary one', function () {
    $reader = User::factory()->accountingReader()->create();
    Booking::factory()->create(['paperless_document_id' => 12]);
    Http::fake(['paperless.test/api/documents/*/thumb/' => Http::response('IMG', 200)]);

    // #12 is linked → allowed.
    $this->actingAs($reader)->get('/accounting/paperless/documents/12/thumb')->assertOk();
    // #99 is not linked to any booking → forbidden (no archive-wide enumeration).
    $this->actingAs($reader)->get('/accounting/paperless/documents/99/thumb')->assertForbidden();
});

it('keeps archive-wide reads (search / find / download) editor-only', function () {
    $reader = User::factory()->accountingReader()->create();
    Http::preventStrayRequests();

    $this->actingAs($reader)->getJson('/accounting/paperless/search?q=x')->assertForbidden();
    $this->actingAs($reader)->getJson('/accounting/paperless/documents/12')->assertForbidden();
    $this->actingAs($reader)->get('/accounting/paperless/documents/12/download')->assertForbidden();
});

it('returns empty search results when paperless is not configured', function () {
    config()->set('services.paperless.url', null);
    Http::preventStrayRequests();

    $this->actingAs($this->admin)
        ->getJson('/accounting/paperless/search?q=rewe')
        ->assertOk()
        ->assertJson(['results' => []]);
});

it('forbids non-accounting users', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->getJson('/accounting/paperless/search?q=rewe')
        ->assertForbidden();
});
