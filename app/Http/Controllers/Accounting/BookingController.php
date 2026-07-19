<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BookingRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\Child;
use App\Models\User;
use App\Support\Accounting\CategoryOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                'counterpartyChild:id,name',
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
                    'counterparty' => $b->counterpartyLabel(),
                    'purpose' => $b->purpose,
                ];
            });

        return Inertia::render('Accounting/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $filters,
            // Drives the „Entwürfe prüfen" button (resume the review workflow anytime).
            'draftCount' => Booking::needsReview()->count(),
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
                'counterparty_child_id' => $booking->counterparty_child_id,
                'counterparty_user_id' => $booking->counterparty_user_id,
                'counterparty_name' => $booking->counterparty_name,
                'status' => $booking->status->value,
            ],
            'statuses' => $this->enumOptions(BookingStatus::cases()),
        ]);
    }

    public function update(BookingRequest $request, Booking $booking): RedirectResponse
    {
        $attributes = $request->toAttributes();
        // The edit form may flip the review status (mark confirmed / back to draft).
        if ($request->filled('status')) {
            $attributes['status'] = BookingStatus::from($request->string('status')->value());
        }

        $booking->update($attributes);

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
     * Step-through review of unconfirmed drafts across the whole ledger, oldest
     * first. Renders one booking at a time; a cursor keeps the place across skips.
     */
    public function review(Request $request): Response|RedirectResponse
    {
        $drafts = Booking::needsReview()->orderBy('booking_date')->orderBy('id');

        $cursor = $request->integer('cursor');
        $booking = (clone $drafts)->when($cursor, fn ($q) => $q->where('id', $cursor))->first()
            ?? (clone $drafts)->first();

        if (! $booking) {
            return redirect()->route('accounting.bookings.index')->with('status', __('flash.import_complete'));
        }

        // A „suggested" booking already carries the AI's picks in its real fields.
        return Inertia::render('Accounting/Bookings/Review', [
            'booking' => [
                'id' => $booking->id,
                'account' => $booking->account?->name,
                'account_id' => $booking->account_id,
                'booking_date' => $booking->booking_date?->format('Y-m-d'),
                'valuta_date' => $booking->valuta_date?->format('Y-m-d'),
                'purpose' => $booking->purpose,
                'comment' => $booking->comment,
                'amount_cents' => $booking->amount_cents,
                // Positive magnitude for the input; the bank sign fixes the direction.
                'amount' => abs($booking->amount_cents) / 100,
                'direction' => $booking->amount_cents >= 0 ? CategoryDirection::Income->value : CategoryDirection::Expense->value,
                'category_id' => $booking->category_id,
                'counterparty_child_id' => $booking->counterparty_child_id,
                'counterparty_user_id' => $booking->counterparty_user_id,
                'counterparty_name' => $booking->counterparty_name,
                'ai_suggested' => $booking->status === BookingStatus::Suggested,
            ],
            'remaining' => (clone $drafts)->count(),
            'accounts' => Account::where('active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => CategoryOptions::flat(),
            'children' => Child::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Confirm / discard / skip the current draft, then advance to the next.
     * Confirm validates and saves the full (possibly edited) booking, just like
     * the normal booking form; the bank sign fixes the income/expense direction.
     */
    public function reviewSave(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(in_array($booking->status, [BookingStatus::Draft, BookingStatus::Suggested], true), 404);

        $action = $request->validate(['action' => ['required', 'in:confirm,discard,skip']])['action'];

        if ($action === 'discard') {
            $booking->delete();
        } elseif ($action === 'confirm') {
            $this->confirmDraft($request, $booking);
        }

        return redirect()->route('accounting.bookings.review', ['cursor' => $this->nextDraftId($booking)]);
    }

    /** Validate the full form and confirm a draft (kind/sign from the category). */
    private function confirmDraft(Request $request, Booking $booking): void
    {
        $data = $request->validate([
            'account_id' => ['required', Rule::exists('accounting_accounts', 'id')],
            'category_id' => ['required', Rule::exists('accounting_categories', 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'booking_date' => ['required', 'date'],
            'valuta_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'counterparty_child_id' => ['nullable', Rule::exists('children', 'id')],
            'counterparty_user_id' => ['nullable', Rule::exists('users', 'id')],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
        ]);

        $category = Category::findOrFail($data['category_id']);
        // The real cash-flow sign is fixed by the bank; the category must match it.
        $expected = $booking->amount_cents >= 0 ? CategoryDirection::Income : CategoryDirection::Expense;

        if ($category->direction !== $expected) {
            throw ValidationException::withMessages(['category_id' => __('accounting.import.wrong_direction')]);
        }

        $booking->update([
            'account_id' => (int) $data['account_id'],
            'category_id' => $category->id,
            'kind' => BookingKind::from($category->direction->value),
            'amount_cents' => (int) round((float) $data['amount'] * 100) * $category->direction->sign(),
            'booking_date' => $data['booking_date'],
            'valuta_date' => ($data['valuta_date'] ?? null) ?: $data['booking_date'],
            'purpose' => $data['purpose'] ?? null,
            'comment' => $data['comment'] ?? null,
            ...$this->counterparty($data),
            'status' => BookingStatus::Confirmed,
        ]);
    }

    /**
     * Resolve the mutually-exclusive counterparty: child (income) beats user
     * (person expense) beats free text.
     *
     * @param  array<string, mixed>  $data
     * @return array{counterparty_child_id: ?int, counterparty_user_id: ?int, counterparty_name: ?string}
     */
    private function counterparty(array $data): array
    {
        $childId = ! empty($data['counterparty_child_id']) ? (int) $data['counterparty_child_id'] : null;
        $userId = ! $childId && ! empty($data['counterparty_user_id']) ? (int) $data['counterparty_user_id'] : null;

        return [
            'counterparty_child_id' => $childId,
            'counterparty_user_id' => $userId,
            'counterparty_name' => ($childId || $userId) ? null : ($data['counterparty_name'] ?? null),
        ];
    }

    /** The next unconfirmed booking after the given one in (booking_date, id) order. */
    private function nextDraftId(Booking $current): ?int
    {
        return Booking::needsReview()
            ->where(function ($q) use ($current): void {
                $q->where('booking_date', '>', $current->booking_date)
                    ->orWhere(function ($q2) use ($current): void {
                        $q2->where('booking_date', $current->booking_date)->where('id', '>', $current->id);
                    });
            })
            ->orderBy('booking_date')
            ->orderBy('id')
            ->value('id');
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
            'children' => Child::orderBy('name')->get(['id', 'name']),
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
