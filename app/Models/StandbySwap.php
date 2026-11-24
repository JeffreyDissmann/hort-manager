<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StandbySwapStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request to exchange two Springerdienst dates: the family of `offeredChild` gives
 * `offeredDuty` and takes `wantedDuty` from the family of `wantedChild`. The rules
 * (who may ask, answer, when it is still valid) live in {@see \App\Support\StandbySwaps}.
 */
class StandbySwap extends Model
{
    protected $fillable = [
        'offered_duty_id',
        'offered_child_id',
        'wanted_duty_id',
        'wanted_child_id',
        'status',
        'requested_by',
        'answered_by',
        'answered_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => StandbySwapStatus::class,
            'answered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StandbyDuty, $this> */
    public function offeredDuty(): BelongsTo
    {
        return $this->belongsTo(StandbyDuty::class, 'offered_duty_id');
    }

    /** @return BelongsTo<StandbyDuty, $this> */
    public function wantedDuty(): BelongsTo
    {
        return $this->belongsTo(StandbyDuty::class, 'wanted_duty_id');
    }

    /** @return BelongsTo<Child, $this> */
    public function offeredChild(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'offered_child_id');
    }

    /** @return BelongsTo<Child, $this> */
    public function wantedChild(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'wanted_child_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @param  Builder<StandbySwap>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', StandbySwapStatus::Pending);
    }

    /**
     * Pending requests that touch the given duty, from either side.
     *
     * @param  Builder<StandbySwap>  $query
     */
    public function scopeTouching(Builder $query, int $dutyId): void
    {
        $query->where(fn (Builder $q) => $q->where('offered_duty_id', $dutyId)->orWhere('wanted_duty_id', $dutyId));
    }

    /**
     * Whether the request still describes the list: both dates still held by the
     * children they were asked for, and neither in the past.
     */
    public function isStillValid(): bool
    {
        $offered = $this->offeredDuty;
        $wanted = $this->wantedDuty;
        $today = Carbon::today()->toDateString();

        return $this->status === StandbySwapStatus::Pending
            && $offered !== null && $wanted !== null
            && $offered->child_id === $this->offered_child_id
            && $wanted->child_id === $this->wanted_child_id
            && $offered->date->toDateString() >= $today
            && $wanted->date->toDateString() >= $today;
    }
}
