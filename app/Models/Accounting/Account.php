<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\BookingStatus;
use Database\Factories\Accounting\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bank or cash account (Konto, Bar-Kasse) that bookings post against. */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $table = 'accounting_accounts';

    protected $fillable = [
        'name',
        'iban',
        'opening_balance_cents',
        'opening_balance_date',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance_cents' => 'integer',
            'opening_balance_date' => 'date:Y-m-d',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Opening balance plus the sum of this account's confirmed bookings, in cents. */
    public function balanceCents(): int
    {
        return $this->opening_balance_cents
            + (int) $this->bookings()->where('status', BookingStatus::Confirmed)->sum('amount_cents');
    }
}
