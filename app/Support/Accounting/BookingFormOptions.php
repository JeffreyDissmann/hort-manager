<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use App\Models\Accounting\Account;
use App\Models\Child;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The shared option lists for the booking create/edit/review forms and the receipt
 * wizard's „create booking" modal: active accounts, the category tree, children (with
 * their enrolment window) and users. One source so the shapes can't drift apart.
 */
class BookingFormOptions
{
    /**
     * @return array{
     *     accounts: Collection<int, Account>,
     *     categories: list<array<string, mixed>>,
     *     children: Collection<int, array{id:int, name:string, active_from:?string, active_until:?string}>,
     *     users: Collection<int, User>,
     * }
     */
    public static function all(): array
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
