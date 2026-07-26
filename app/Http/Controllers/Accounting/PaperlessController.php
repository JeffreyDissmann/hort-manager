<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\PaperlessMatcher;
use App\Services\Accounting\PaperlessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin proxy between the booking form and the Paperless archive. Search + lookup
 * return JSON for the picker; thumbnail + download stream the binary through the app
 * so the API token never reaches the browser and the user needs no direct Paperless
 * access. Everything 404s / returns empty when the integration is not configured.
 */
class PaperlessController extends Controller
{
    public function __construct(private readonly PaperlessService $paperless) {}

    /** Full-text search for the picker dropdown. */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        return response()->json(['results' => $this->paperless->search($query)]);
    }

    /**
     * Suggest the best-matching document for the current booking form („KI-Vorschlag").
     * Works from loose form fields so it serves both the create and edit forms.
     */
    public function suggest(Request $request, PaperlessMatcher $matcher): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['nullable', 'string', 'max:2000'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric'],
            'date' => ['nullable', 'date'],
        ]);

        return response()->json($matcher->match($data));
    }

    /** Resolve a single document (the paste-id / paste-URL flow). */
    public function find(int $document): JsonResponse
    {
        $found = $this->paperless->find($document);

        abort_if($found === null, Response::HTTP_NOT_FOUND);

        return response()->json($found);
    }

    /** Stream the document thumbnail. */
    public function thumbnail(int $document): StreamedResponse
    {
        return $this->stream($this->paperless->thumbnail($document), 'image/webp');
    }

    /** Stream the original document file. */
    public function download(int $document): StreamedResponse
    {
        return $this->stream($this->paperless->download($document), 'application/octet-stream');
    }

    /** Relay an upstream Paperless binary response, or 404 when it isn't available. */
    private function stream(?\Illuminate\Http\Client\Response $upstream, string $fallbackType): StreamedResponse
    {
        abort_if($upstream === null, Response::HTTP_NOT_FOUND);

        $contentType = $upstream->header('Content-Type') ?: $fallbackType;

        return response()->stream(function () use ($upstream): void {
            echo $upstream->body();
        }, Response::HTTP_OK, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
