<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BookingRequest;
use App\Jobs\SyncPaperlessBookingLink;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Child;
use App\Models\User;
use App\Services\Accounting\PaperlessService;
use App\Support\Accounting\CategoryOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * „Belege zuordnen" — a wizard that walks the unlinked Paperless receipts and, for each,
 * either attaches it to a matching booking or creates one from it. Gated behind finishing
 * the AI review, so the booking set is stable (and drafts have had their chance to auto-link).
 */
class PaperlessReviewController extends Controller
{
    public function __construct(private readonly PaperlessService $paperless) {}

    public function index(Request $request): Response
    {
        // Gate: finish reviewing drafts/AI suggestions first.
        $open = Booking::needsReview()->count();
        if ($open > 0) {
            return Inertia::render('Accounting/Paperless/Review', [
                'gate' => ['count' => $open],
                'documents' => [],
                'range' => null,
                'paperlessEnabled' => $this->paperless->enabled(),
            ]);
        }

        // Default the range to the booked period; the user can narrow it.
        $min = Booking::min('booking_date') ?? Carbon::now()->startOfYear()->toDateString();
        $max = Booking::max('booking_date') ?? Carbon::now()->toDateString();
        $from = (string) $request->query('from', $min);
        $to = (string) $request->query('to', $max);
        $payment = (string) $request->query('payment', '');

        $documents = $this->withCandidates($this->paperless->reviewCandidates($from, $to, $payment ?: null));

        return Inertia::render('Accounting/Paperless/Review', [
            'gate' => null,
            'documents' => $documents,
            'range' => ['from' => $from, 'to' => $to, 'min' => $min, 'max' => $max, 'payment' => $payment],
            'paymentOptions' => $this->paperless->paymentOptions(),
            'paperlessEnabled' => $this->paperless->enabled(),
            'paperlessUrl' => $this->paperless->baseUrl(),
            ...$this->bookingFormProps(),
        ]);
    }

    /** Attach the chosen (unlinked, non-transfer) booking to the document. */
    public function attach(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'min:1'],
            'document_title' => ['nullable', 'string', 'max:255'],
            'booking_id' => ['required', 'integer', 'exists:accounting_bookings,id'],
        ]);

        $booking = Booking::findOrFail($data['booking_id']);
        abort_if($booking->kind === BookingKind::Transfer || $booking->paperless_document_id !== null, HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

        $booking->update([
            'paperless_document_id' => $data['document_id'],
            'paperless_document_title' => $data['document_title'] ?? null,
        ]);
        SyncPaperlessBookingLink::dispatch($booking->id);

        return back()->with('status', __('flash.receipt_attached'));
    }

    /** Create a confirmed booking from a receipt (fields prefilled in the wizard) and link it. */
    public function createBooking(BookingRequest $request): RedirectResponse
    {
        $booking = Booking::create([...$request->toAttributes(), 'status' => BookingStatus::Confirmed]);

        if ($booking->paperless_document_id) {
            SyncPaperlessBookingLink::dispatch($booking->id);
        }

        return back()->with('status', __('flash.booking_created'));
    }

    /** Mark a document „not a booking" so it drops out of the wizard. */
    public function ignore(Request $request): RedirectResponse
    {
        $data = $request->validate(['document_id' => ['required', 'integer', 'min:1']]);

        $this->paperless->ignore($data['document_id']);

        return back()->with('status', __('flash.receipt_ignored'));
    }

    /**
     * Attach matching-booking candidates to each document: unlinked, non-transfer bookings
     * whose amount equals the document total (either sign), ranked by date proximity. One
     * grouped query, matched in PHP — no per-document query.
     *
     * @param  list<array{id:int, title:string, created:?string, correspondent?:?string, amount_cents?:?int}>  $documents
     * @return list<array<string, mixed>>
     */
    private function withCandidates(array $documents): array
    {
        $paths = collect(CategoryOptions::flat(onlyActive: false))->keyBy('id');

        $byAmount = Booking::query()
            ->whereNull('paperless_document_id')
            ->where('kind', '!=', BookingKind::Transfer)
            ->with(['account:id,name', 'counterparty:id,name', 'counterpartyChild:id,name'])
            ->get()
            ->groupBy(fn (Booking $b): int => abs($b->amount_cents));

        return array_map(function (array $document) use ($byAmount, $paths): array {
            $amount = (int) ($document['amount_cents'] ?? 0);
            $reference = $document['created'] ? Carbon::parse($document['created']) : null;

            $candidates = ($byAmount[$amount] ?? collect())
                ->sortBy(fn (Booking $b): float => $reference
                    ? abs($reference->diffInDays($b->valuta_date ?? $b->booking_date))
                    : 0.0)
                ->take(5)
                ->map(fn (Booking $b): array => [
                    'id' => $b->id,
                    'booking_date' => $b->booking_date?->format('Y-m-d'),
                    'category' => $paths->get($b->category_id)['path'] ?? null,
                    'counterparty' => $b->counterpartyLabel(),
                    'account' => $b->account?->name,
                    'amount_cents' => $b->amount_cents,
                ])
                ->values()
                ->all();

            return [...$document, 'candidates' => $candidates];
        }, $documents);
    }

    /**
     * Options for the „create booking" modal (mirrors BookingController::formProps).
     *
     * @return array<string, mixed>
     */
    private function bookingFormProps(): array
    {
        return [
            'accounts' => Account::where('active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => CategoryOptions::flat(),
            'children' => Child::orderBy('name')->get(['id', 'name', 'active_from', 'active_until'])
                ->map(fn (Child $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'active_from' => $c->active_from?->format('Y-m-d'),
                    'active_until' => $c->active_until?->format('Y-m-d'),
                ]),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}
