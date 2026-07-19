<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\User;
use Database\Factories\Accounting\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * A single ledger entry. `amount_cents` is signed real cash flow (income +,
 * expense −, transfer ±), so an account balance is just the sum of its rows.
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $table = 'accounting_bookings';

    protected $fillable = [
        'account_id',
        'category_id',
        'import_id',
        'transfer_id',
        'kind',
        'status',
        'amount_cents',
        'currency',
        'booking_date',
        'valuta_date',
        'purpose',
        'comment',
        'counterparty_user_id',
        'counterparty_name',
        'suggested_category_id',
        'suggested_counterparty_user_id',
        'suggested_counterparty_name',
        'ai_suggested_at',
        'import_hash',
    ];

    protected function casts(): array
    {
        return [
            'kind' => BookingKind::class,
            'status' => BookingStatus::class,
            'amount_cents' => 'integer',
            'booking_date' => 'date:Y-m-d',
            'valuta_date' => 'date:Y-m-d',
            'ai_suggested_at' => 'datetime',
        ];
    }

    /** Stamp who created / last touched a booking, for the „wer" audit columns. */
    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $userId = Auth::id();
            $booking->created_by ??= $userId;
            $booking->updated_by ??= $userId;
        });

        static::updating(function (Booking $booking): void {
            $booking->updated_by = Auth::id() ?? $booking->updated_by;
        });
    }

    /** @param Builder<Booking> $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', BookingStatus::Confirmed);
    }

    /** @param Builder<Booking> $query */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', BookingStatus::Draft);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Import, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /** @return BelongsTo<Transfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function suggestedCounterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_counterparty_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
