<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\BookingKind;
use App\Models\User;
use Database\Factories\Accounting\TransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    /**
     * Record an internal transfer: an expense leg on the source account and an
     * income leg on the target, both kind=transfer with no category, linked here.
     */
    public static function record(
        int $fromAccountId,
        int $toAccountId,
        int $amountCents,
        string $bookingDate,
        ?string $valutaDate = null,
        ?string $purpose = null,
        ?string $comment = null,
    ): self {
        $magnitude = abs($amountCents);

        return DB::transaction(function () use ($fromAccountId, $toAccountId, $magnitude, $bookingDate, $valutaDate, $purpose, $comment): self {
            $leg = fn (int $accountId, int $signed): Booking => Booking::create([
                'account_id' => $accountId,
                'category_id' => null,
                'kind' => BookingKind::Transfer,
                'amount_cents' => $signed,
                'booking_date' => $bookingDate,
                'valuta_date' => $valutaDate ?: $bookingDate,
                'purpose' => $purpose,
                'comment' => $comment,
            ]);

            $out = $leg($fromAccountId, -$magnitude);
            $in = $leg($toAccountId, $magnitude);

            $transfer = static::create([
                'out_booking_id' => $out->id,
                'in_booking_id' => $in->id,
                'created_by' => Auth::id(),
            ]);

            Booking::whereKey([$out->id, $in->id])->update(['transfer_id' => $transfer->id]);

            return $transfer;
        });
    }

    /** Delete a transfer together with both of its booking legs. */
    public function deleteWithLegs(): void
    {
        DB::transaction(function (): void {
            $legIds = [$this->out_booking_id, $this->in_booking_id];
            $this->delete();
            Booking::whereKey($legIds)->get()->each->delete();
        });
    }

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
