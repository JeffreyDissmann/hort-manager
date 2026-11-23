<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BookingRequest;
use App\Jobs\SyncPaperlessBookingLink;
use App\Models\Accounting\Booking;
use App\Services\Accounting\PaperlessService;
use App\Support\Accounting\BookingFormOptions;
use App\Support\Accounting\CategoryOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
            ...BookingFormOptions::all(),
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
        // The booking must be unlinked + not a transfer, and the document must not already
        // be attached to another booking (one document per booking).
        abort_if(
            $booking->kind === BookingKind::Transfer
            || $booking->paperless_document_id !== null
            || Booking::where('paperless_document_id', $data['document_id'])->exists(),
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        );

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

    /** How far (days) from the document date a booking may lie to count as an alternative. */
    private const ALTERNATIVE_WINDOW_DAYS = 14;

    /** How many bookings each list (exact matches, alternatives) offers at most. */
    private const MAX_OPTIONS = 5;

    /**
     * Attach booking options to each document, from unlinked, non-transfer bookings:
     *
     * - `candidates` — the amount equals the document total (either sign), ranked by date
     *   proximity;
     * - `alternatives` — the rest of the bookings within ±{@see ALTERNATIVE_WINDOW_DAYS}
     *   days of the document date, for receipts whose booking differs in amount (a tip,
     *   a partial refund, a typo in the amount field), ranked by amount closeness with a
     *   counterparty that names the correspondent first.
     *
     * Documents with an exact candidate come first, so the confident work is done before
     * the guesswork. Two grouped queries, matched in PHP — no per-document query.
     *
     * @param  list<array{id:int, title:string, created:?string, correspondent?:?string, document_type?:?string, tags?:list<string>, amount_cents?:?int}>  $documents
     * @return list<array<string, mixed>>
     */
    private function withCandidates(array $documents): array
    {
        $paths = collect(CategoryOptions::flat(onlyActive: false))->keyBy('id');
        $relations = ['account:id,name', 'counterparty:id,name', 'counterpartyChild:id,name'];

        // Only load bookings whose amount can actually match a document (both signs), so
        // this stays bounded (≤ 2× the page) instead of loading the whole ledger.
        $amounts = collect($documents)
            ->pluck('amount_cents')
            ->filter()
            ->flatMap(fn ($c): array => [abs((int) $c), -abs((int) $c)])
            ->unique()
            ->values();

        $byAmount = $amounts->isEmpty()
            ? collect()
            : Booking::query()
                ->whereNull('paperless_document_id')
                ->where('kind', '!=', BookingKind::Transfer)
                ->whereIn('amount_cents', $amounts)
                ->with($relations)
                ->get()
                ->groupBy(fn (Booking $b): int => abs($b->amount_cents));

        $nearby = $this->bookingsAround($documents, $relations);

        $shape = fn (Booking $b): array => [
            'id' => $b->id,
            'booking_date' => $b->booking_date?->format('Y-m-d'),
            'category' => $paths->get($b->category_id)['path'] ?? null,
            'counterparty' => $b->counterpartyLabel(),
            'account' => $b->account?->name,
            'amount_cents' => $b->amount_cents,
        ];

        $withOptions = array_map(function (array $document) use ($byAmount, $nearby, $shape): array {
            $amount = abs((int) ($document['amount_cents'] ?? 0));
            $reference = $document['created'] ? Carbon::parse($document['created']) : null;
            $daysOff = fn (Booking $b): float => $reference
                ? abs($reference->diffInDays($b->valuta_date ?? $b->booking_date))
                : 0.0;

            $exact = ($byAmount[$amount] ?? collect())->sortBy($daysOff)->take(self::MAX_OPTIONS);

            $alternatives = $reference === null
                ? collect()
                : $nearby
                    ->reject(fn (Booking $b): bool => abs($b->amount_cents) === $amount)
                    ->filter(fn (Booking $b): bool => $daysOff($b) <= self::ALTERNATIVE_WINDOW_DAYS)
                    ->sortBy([
                        fn (Booking $a, Booking $b): int => $this->namesCorrespondent($b, $document) <=> $this->namesCorrespondent($a, $document),
                        fn (Booking $a, Booking $b): int => abs(abs($a->amount_cents) - $amount) <=> abs(abs($b->amount_cents) - $amount),
                        fn (Booking $a, Booking $b): int => $daysOff($a) <=> $daysOff($b),
                    ])
                    ->take(self::MAX_OPTIONS);

            return [
                ...$document,
                'candidates' => $exact->map($shape)->values()->all(),
                'alternatives' => $alternatives->map($shape)->values()->all(),
            ];
        }, $documents);

        // Stable: within each group the documents keep Paperless' date order.
        usort($withOptions, fn (array $a, array $b): int => ($b['candidates'] !== []) <=> ($a['candidates'] !== []));

        return $withOptions;
    }

    /**
     * Unlinked, non-transfer bookings dated within the alternative window of any document.
     * Filtered on the booking date with a margin, since the ranking measures from the
     * valuta date, which can lie a few days off.
     *
     * @param  list<array<string, mixed>>  $documents
     * @param  list<string>  $relations
     * @return Collection<int, Booking>
     */
    private function bookingsAround(array $documents, array $relations): Collection
    {
        $dates = collect($documents)->pluck('created')->filter()->map(fn (string $d): Carbon => Carbon::parse($d));

        if ($dates->isEmpty()) {
            return collect();
        }

        $margin = self::ALTERNATIVE_WINDOW_DAYS + 7;

        return Booking::query()
            ->whereNull('paperless_document_id')
            ->where('kind', '!=', BookingKind::Transfer)
            ->whereBetween('booking_date', [
                $dates->min()->copy()->subDays($margin)->toDateString(),
                $dates->max()->copy()->addDays($margin)->toDateString(),
            ])
            ->with($relations)
            ->get();
    }

    /**
     * Whether the booking's counterparty or purpose mentions the document's correspondent —
     * the strongest hint that a booking with a different amount still belongs to it.
     *
     * @param  array<string, mixed>  $document
     */
    private function namesCorrespondent(Booking $booking, array $document): bool
    {
        $correspondent = mb_strtolower(trim((string) ($document['correspondent'] ?? '')));

        if ($correspondent === '') {
            return false;
        }

        $haystack = mb_strtolower(($booking->counterpartyLabel() ?? '').' '.($booking->purpose ?? ''));

        return str_contains($haystack, $correspondent);
    }
}
