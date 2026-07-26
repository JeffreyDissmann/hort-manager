<?php

declare(strict_types=1);

use App\Ai\Agents\PaperlessMatcher as PaperlessMatcherAgent;
use App\Models\User;
use App\Services\Accounting\PaperlessMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.paperless.url', 'https://paperless.test');
    config()->set('services.paperless.token', 'secret-token');
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
    PaperlessMatcherAgent::fake([['document_id' => 44, 'confidence' => 'high']]);

    $result = app(PaperlessMatcher::class)->match([
        'purpose' => 'REWE SAGT DANKE',
        'counterparty' => 'REWE',
        'amount' => 19.95,
        'date' => '2026-03-31',
    ]);

    expect($result['candidates'])->toHaveCount(2)
        ->and($result['best'])->toMatchArray(['id' => 44, 'title' => 'REWE-Einkaufsbeleg Lebensmittel', 'confidence' => 'high']);
});

it('returns no best pick when the AI declines', function () {
    fakePaperlessSearch([['id' => 44, 'title' => 'REWE', 'created' => '2026-03-30']]);
    PaperlessMatcherAgent::fake([['document_id' => null, 'confidence' => 'low']]);

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
    PaperlessMatcherAgent::fake([['document_id' => 44, 'confidence' => 'high']]); // must not be consumed

    $result = app(PaperlessMatcher::class)->match(['purpose' => 'nichts']);

    expect($result)->toBe(['best' => null, 'candidates' => []]);
});

it('is empty when the query has no identifying fields', function () {
    Http::preventStrayRequests();

    expect(app(PaperlessMatcher::class)->match(['purpose' => '', 'counterparty' => null]))
        ->toBe(['best' => null, 'candidates' => []]);
});

it('exposes the suggest endpoint to accounting editors', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    fakePaperlessSearch([['id' => 44, 'title' => 'REWE-Beleg', 'created' => '2026-03-30']]);
    PaperlessMatcherAgent::fake([['document_id' => 44, 'confidence' => 'high']]);

    $this->actingAs($admin)
        ->postJson('/accounting/paperless/suggest', [
            'purpose' => 'REWE SAGT DANKE',
            'counterparty' => 'REWE',
            'amount' => 19.95,
            'date' => '2026-03-31',
        ])
        ->assertOk()
        ->assertJson(['best' => ['id' => 44, 'confidence' => 'high']]);
});
