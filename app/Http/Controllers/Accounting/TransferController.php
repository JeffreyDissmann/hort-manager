<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\TransferRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transfer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Admin-only creation of internal transfers between own accounts. */
class TransferController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Accounting/Transfers/Create', [
            'accounts' => Account::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(TransferRequest $request): RedirectResponse
    {
        Transfer::record(
            fromAccountId: $request->integer('from_account_id'),
            toAccountId: $request->integer('to_account_id'),
            amountCents: (int) round((float) $request->input('amount') * 100),
            bookingDate: $request->date('booking_date')->toDateString(),
            valutaDate: $request->input('valuta_date') ?: null,
            purpose: $request->input('purpose'),
            comment: $request->input('comment'),
        );

        return redirect()
            ->route('accounting.bookings.index')
            ->with('status', __('flash.transfer_created'));
    }
}
