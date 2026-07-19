<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\User;
use Database\Factories\Accounting\ImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One bank-statement CSV upload; its draft bookings reference it. */
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    protected $table = 'accounting_imports';

    protected $fillable = [
        'account_id',
        'uploaded_by',
        'filename',
        'row_count',
        'imported_count',
        'duplicate_count',
    ];

    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'imported_count' => 'integer',
            'duplicate_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
