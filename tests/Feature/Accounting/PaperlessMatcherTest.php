<?php

declare(strict_types=1);

use App\Ai\Agents\ReceiptMatcher;
use App\Models\Accounting\Booking;
use App\Services\Accounting\PaperlessMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
    config()->set('services.paperless.booking_field', null);
    config()->set('services.paperless.amount_field', null);
    config()->set('accounting.ai_suggestions', true);
});

function fakePaperlessSearch(array $results): void
{
    Http::fake(['paperless.test/api/documents*' => Http::response(['results' => $results])]);
}

it('shortlists candidates and lets the AI pick the best match', function () {
    fakePaperlessSearch([
        ['id' => 44, 'title' => 'REWE-Einkaufsbeleg Lebensmittel', 'created' => '2026-03-30'],
        ['id' => 50, 'title' => 'Lebensmittel-Einkaufsbeleg', 'created' => '2026-03-28'],
    ]);
    ReceiptMatcher::fake([['document_id' => 44, 'confidence' => 'high']]);

    $result = app(PaperlessMatcher::class)->match([
        'purpose' => 'REWE SAGT DANKE',
        'counterparty' => 'REWE',
        'amount' => 19.95,
        'date' => '2026-03-31',
    ]);

    expect($result['candidates'])->toHaveCount(2)
        ->and($result['best'])->toMatchArray(['id' => 44, 'title' => 'REWE-Einkaufsbeleg Lebensmittel', 'confidence' => 'high']);
});

it('excludes documents already linked to a booking from matching', function () {
    Booking::factory()->create(['paperless_document_id' => 44]);
    fakePaperlessSearch([
        ['id' => 44, 'title' => 'Already linked', 'created' => '2026-03-30', 'content' => 'x'],
        ['id' => 50, 'title' => 'Free receipt', 'created' => '2026-03-28', 'content' => 'y'],
    ]);
    ReceiptMatcher::fake([['document_id' => 50, 'confidence' => 'high']]);

    $result = app(PaperlessMatcher::class)->match(['purpose' => 'REWE']);

    expect(collect($result['candidates'])->pluck('id')->all())->toBe([50])
        ->and($result['best']['id'])->toBe(50);
});

it('returns no best pick when the AI declines', function () {
    fakePaperlessSearch([['id' => 44, 'title' => 'REWE', 'created' => '2026-03-30']]);
    ReceiptMatcher::fake([['document_id' => null, 'confidence' => 'low']]);

    $result = app(PaperlessMatcher::class)->match(['purpose' => 'unklar', 'counterparty' => 'x']);

    expect($result['best'])->toBeNull()
        ->and($result['candidates'])->toHaveCount(1);
});

it('returns candidates but no best pick when AI is disabled', function () {
    config()->set('accounting.ai_suggestions', false);
    fakePaperlessSearch([['id' => 44, 'title' => 'REWE', 'created' => '2026-03-30']]);

    $result = app(PaperlessMatcher::class)->match(['purpose' => 'REWE']);

    expect($result['best'])->toBeNull()
        ->and($result['candidates'])->toHaveCount(1);
});

it('returns nothing when there are no candidates and never calls the AI', function () {
    fakePaperlessSearch([]);
    ReceiptMatcher::fake([['document_id' => 44, 'confidence' => 'high']]); // must not be consumed

    $result = app(PaperlessMatcher::class)->match(['purpose' => 'nichts']);

    expect($result)->toBe(['best' => null, 'candidates' => []]);
});

it('is empty when the query has no identifying fields', function () {
    Http::preventStrayRequests();

    expect(app(PaperlessMatcher::class)->match(['purpose' => '', 'counterparty' => null]))
        ->toBe(['best' => null, 'candidates' => []]);
});
