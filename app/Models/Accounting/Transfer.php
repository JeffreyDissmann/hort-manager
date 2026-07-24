<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
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

    /**
     * Turn an existing (imported) booking into an internal transfer: reuse it as its
     * own leg and create the matching opposite leg on the other account, so the money
     * isn't double-counted. Used to reclassify e.g. a bank cash withdrawal as a
     * Hort-Konto → Bar-Kasse transfer straight from the review.
     */
    public static function fromBooking(Booking $booking, int $otherAccountId): self
    {
        return DB::transaction(function () use ($booking, $otherAccountId): self {
            // The matching opposite leg on the other account (negated amount).
            $otherLeg = Booking::create([
                'account_id' => $otherAccountId,
                'category_id' => null,
                'kind' => BookingKind::Transfer,
                'status' => BookingStatus::Confirmed,
                'amount_cents' => -$booking->amount_cents,
                'currency' => $booking->currency,
                'booking_date' => $booking->booking_date->toDateString(),
                'valuta_date' => ($booking->valuta_date ?? $booking->booking_date)->toDateString(),
                'purpose' => $booking->purpose,
                'comment' => $booking->comment,
            ]);

            // Reuse the original booking as its own leg — no category, no counterparty.
            $booking->update([
                'kind' => BookingKind::Transfer,
                'status' => BookingStatus::Confirmed,
                'category_id' => null,
                'counterparty_child_id' => null,
                'counterparty_user_id' => null,
                'counterparty_name' => null,
                'confidence' => null,
            ]);

            // out = the negative (source) leg, in = the positive (target) leg.
            [$out, $in] = $booking->amount_cents < 0 ? [$booking, $otherLeg] : [$otherLeg, $booking];

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
            // Booking has no "deleting" hook, so a mass delete is equivalent (and cheaper).
            Booking::whereKey($legIds)->delete();
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
