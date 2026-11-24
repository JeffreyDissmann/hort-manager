<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandbySwapSlackMessage extends Model
{
    protected $fillable = ['standby_swap_id', 'user_id', 'channel', 'ts'];

    /** @return BelongsTo<StandbySwap, $this> */
    public function swap(): BelongsTo
    {
        return $this->belongsTo(StandbySwap::class, 'standby_swap_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
