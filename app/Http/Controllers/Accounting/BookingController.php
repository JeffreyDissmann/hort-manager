<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BookingRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\User;
use App\Support\Accounting\CategoryOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Admin-only list + manual entry of ledger bookings. */
class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['account', 'category', 'kind', 'status', 'from', 'to', 'search']);
        $paths = collect(CategoryOptions::flat(onlyActive: false))->keyBy('id');

        $bookings = Booking::query()
            ->with([
                'account:id,name',
                'counterparty:id,name',
                'transfer.outBooking.account:id,name',
                'transfer.inBooking.account:id,name',
            ])
            ->when($filters['account'] ?? null, fn ($q, $v) => $q->where('account_id', $v))
            // A category filter includes the whole subtree (parent + all descendants).
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->whereIn('category_id', $this->categorySubtreeIds((int) $v)))
            ->when($filters['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('booking_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('booking_date', '<=', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('purpose', 'like', "%{$v}%")
                ->orWhere('counterparty_name', 'like', "%{$v}%")))
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(function (Booking $b) use ($paths): array {
                $isTransfer = $b->kind === BookingKind::Transfer;

                // For a transfer leg, the „counterparty" is the other account.
                $counterAccount = null;
                if ($isTransfer && $b->transfer) {
                    $other = $b->transfer->out_booking_id === $b->id
                        ? $b->transfer->inBooking
                        : $b->transfer->outBooking;
                    $counterAccount = $other?->account?->name;
                }

                return [
                    'id' => $b->id,
                    'booking_date' => $b->booking_date?->format('Y-m-d'),
                    'account' => $b->account?->name,
                    'category' => $isTransfer ? null : ($paths->get($b->category_id)['path'] ?? null),
                    'kind' => $b->kind->value,
                    'is_transfer' => $isTransfer,
                    'counter_account' => $counterAccount,
                    'status' => $b->status->value,
                    'amount_cents' => $b->amount_cents,
                    'counterparty' => $b->counterparty?->name ?? $b->counterparty_name,
                    'purpose' => $b->purpose,
                ];
            });

        return Inertia::render('Accounting/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'filterOptions' => [
                'accounts' => Account::orderBy('name')->get(['id', 'name']),
                'categories' => CategoryOptions::flat(onlyActive: false),
                'kinds' => $this->enumOptions(BookingKind::cases()),
                'statuses' => $this->enumOptions(BookingStatus::cases()),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounting/Bookings/Create', $this->formProps());
    }

    public function store(BookingRequest $request): RedirectResponse
    {
        Booking::create([...$request->toAttributes(), 'status' => BookingStatus::Confirmed]);

        return redirect()
            ->route('accounting.bookings.index')
            ->with('status', __('flash.booking_created'));
    }

    public function edit(Booking $booking): Response
    {
        return Inertia::render('Accounting/Bookings/Edit', [
            ...$this->formProps(),
            'booking' => [
                'id' => $booking->id,
                'account_id' => $booking->account_id,
                'category_id' => $booking->category_id,
                // Positive magnitude in euros for the input; sign is re-derived on save.
                'amount' => abs($booking->amount_cents) / 100,
                'booking_date' => $booking->booking_date?->format('Y-m-d'),
                'valuta_date' => $booking->valuta_date?->format('Y-m-d'),
                'purpose' => $booking->purpose,
                'comment' => $booking->comment,
                'counterparty_user_id' => $booking->counterparty_user_id,
                'counterparty_name' => $booking->counterparty_name,
            ],
        ]);
    }

    public function update(BookingRequest $request, Booking $booking): RedirectResponse
    {
        // Status is preserved (a reviewed booking stays confirmed; a draft stays draft).
        $booking->update($request->toAttributes());

        return redirect()
            ->route('accounting.bookings.index')
            ->with('status', __('flash.booking_updated'));
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        // A transfer leg can't be deleted alone — remove the whole transfer.
        if ($booking->transfer) {
            $booking->transfer->deleteWithLegs();
        } else {
            $booking->delete();
        }

        return redirect()
            ->route('accounting.bookings.index')
            ->with('status', __('flash.booking_deleted'));
    }

    /**
     * The given category plus all of its descendants (so filtering by a parent
     * catches bookings on its children too).
     *
     * @return list<int>
     */
    private function categorySubtreeIds(int $rootId): array
    {
        $childrenByParent = Category::query()
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $stack = [$rootId];

        while ($stack !== []) {
            $id = array_pop($stack);
            $ids[] = $id;

            foreach ($childrenByParent->get($id, collect()) as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Shared props for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'accounts' => Account::where('active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => CategoryOptions::flat(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @param  array<int, BookingKind|BookingStatus>  $cases
     * @return list<array{value:string, label:string}>
     */
    private function enumOptions(array $cases): array
    {
        return collect($cases)
            ->map(fn (BookingKind|BookingStatus $case): array => ['value' => $case->value, 'label' => $case->label()])
            ->all();
    }
}
