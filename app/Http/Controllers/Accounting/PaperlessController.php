<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Booking;
use App\Services\Accounting\PaperlessMatcher;
use App\Services\Accounting\PaperlessService;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thin proxy between the booking form and the Paperless archive. Search + lookup
 * return JSON for the picker; thumbnail + download stream the binary through the app
 * so the API token never reaches the browser and the user needs no direct Paperless
 * access. Everything 404s / returns empty when the integration is not configured.
 */
class PaperlessController extends Controller
{
    public function __construct(private readonly PaperlessService $paperless) {}

    /** Full-text search for the picker dropdown; already-linked documents are hidden. */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        return response()->json([
            'results' => $this->paperless->search($query, excludeIds: Booking::linkedDocumentIds()),
        ]);
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

    /** Relay the document thumbnail through the app. */
    public function thumbnail(int $document): HttpResponse
    {
        return $this->relay($this->paperless->thumbnail($document), 'image/webp');
    }

    /** Relay the original document file through the app. */
    public function download(int $document): HttpResponse
    {
        return $this->relay($this->paperless->download($document), 'application/octet-stream');
    }

    /** Relay an upstream Paperless binary response, or 404 when it isn't available. */
    private function relay(?ClientResponse $upstream, string $fallbackType): HttpResponse
    {
        abort_if($upstream === null, Response::HTTP_NOT_FOUND);

        return response($upstream->body(), Response::HTTP_OK, [
            'Content-Type' => $upstream->header('Content-Type') ?: $fallbackType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
