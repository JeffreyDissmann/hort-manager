<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Booking;
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
 * access. Search/find/download are editor-only (archive-wide reads); the thumbnail is
 * readable but scoped to documents linked to a booking. Everything 404s / returns
 * empty when the integration is not configured.
 */
class PaperlessController extends Controller
{
    public function __construct(private readonly PaperlessService $paperless) {}

    /**
     * Full-text search for the picker (typed query or the booking's own „similar docs"
     * suggestions). Already-linked documents are hidden.
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $limit = max(1, min((int) $request->integer('limit', 8), 20));

        // A booking context (amount and/or valuta date) → precise suggestions ranked by
        // exact amount then date proximity. A bare query is a plain full-text search.
        $amount = $request->filled('amount') ? abs((float) $request->input('amount')) : null;
        $near = (string) $request->query('near', '');

        $results = ($amount !== null || $near !== '')
            ? $this->paperless->candidatesFor($query, $amount, $near ?: null, limit: $limit, withCorrespondent: true)
            : $this->paperless->search($query, limit: $limit, withCorrespondent: true);

        return response()->json(['results' => $results]);
    }

    /** Resolve a single document (the paste-id / paste-URL flow). */
    public function find(int $document): JsonResponse
    {
        $found = $this->paperless->find($document);

        abort_if($found === null, Response::HTTP_NOT_FOUND);

        return response()->json($found);
    }

    /**
     * Relay the document thumbnail. Readable, but a read-only user may only fetch the
     * thumbnail of a document that's actually linked to a booking — otherwise they could
     * enumerate the whole archive. The type is forced to the known thumbnail format so a
     * malicious document can't be served as inline HTML/SVG.
     */
    public function thumbnail(Request $request, int $document): HttpResponse
    {
        abort_unless($this->mayView($request, $document), Response::HTTP_FORBIDDEN);

        $body = $this->relayBody($this->paperless->thumbnail($document));

        return response($body, Response::HTTP_OK, [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Relay the original document file as a forced download (editors only, see routes). */
    public function download(int $document): HttpResponse
    {
        $body = $this->relayBody($this->paperless->download($document));

        return response($body, Response::HTTP_OK, [
            'Content-Type' => 'application/octet-stream',
            // Force a download so a document stored as HTML/SVG can't execute same-origin.
            'Content-Disposition' => 'attachment; filename="beleg-'.$document.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** May the requester view this document? Editors always; readers only if it's linked. */
    private function mayView(Request $request, int $document): bool
    {
        return $request->user()->canWriteAccounting()
            || Booking::where('paperless_document_id', $document)->exists();
    }

    /** The upstream binary body, or 404 when Paperless didn't return it. */
    private function relayBody(?ClientResponse $upstream): string
    {
        abort_if($upstream === null, Response::HTTP_NOT_FOUND);

        return $upstream->body();
    }
}
