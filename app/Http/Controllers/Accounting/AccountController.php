<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountRequest;
use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Admin-only CRUD for Buchhaltung accounts (Konto, Bar-Kasse). */
class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = Account::query()
            ->withCount('bookings')
            ->withSum(['bookings as confirmed_cents' => fn (Builder $q) => $q->confirmed()], 'amount_cents')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'iban' => $account->iban,
                'active' => $account->active,
                'opening_balance_cents' => $account->opening_balance_cents,
                'opening_balance_date' => $account->opening_balance_date?->format('Y-m-d'),
                'balance_cents' => $account->opening_balance_cents + (int) $account->confirmed_cents,
                'bookings_count' => $account->bookings_count,
            ]);

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounting/Accounts/Create');
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $account = Account::create($request->toAttributes());

        return redirect()
            ->route('accounting.accounts.index')
            ->with('status', __('flash.account_created', ['name' => $account->name]));
    }

    public function edit(Account $account): Response
    {
        return Inertia::render('Accounting/Accounts/Edit', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'iban' => $account->iban,
                'active' => $account->active,
                // Euros for the <input> to display; stored back as cents.
                'opening_balance' => $account->opening_balance_cents / 100,
                'opening_balance_date' => $account->opening_balance_date?->format('Y-m-d'),
            ],
        ]);
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->toAttributes());

        return redirect()
            ->route('accounting.accounts.index')
            ->with('status', __('flash.account_updated', ['name' => $account->name]));
    }

    public function destroy(Account $account): RedirectResponse
    {
        // Deleting an account cascades to its bookings — refuse if any exist so
        // financial records can't be wiped by accident. Deactivate instead.
        if ($account->bookings()->exists()) {
            return back()->with('status', __('flash.account_has_bookings', ['name' => $account->name]));
        }

        $name = $account->name;
        $account->delete();

        return redirect()
            ->route('accounting.accounts.index')
            ->with('status', __('flash.account_deleted', ['name' => $name]));
    }
}
