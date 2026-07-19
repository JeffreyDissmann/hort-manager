<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\User;
use Database\Factories\Accounting\TransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Links the two booking legs of an internal transfer between own accounts. */
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    protected $table = 'accounting_transfers';

    protected $fillable = [
        'out_booking_id',
        'in_booking_id',
        'created_by',
    ];

    /** @return BelongsTo<Booking, $this> */
    public function outBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'out_booking_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function inBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'in_booking_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
