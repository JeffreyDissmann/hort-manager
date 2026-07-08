<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanionSlackMessage extends Model
{
    protected $fillable = ['daily_departure_id', 'user_id', 'channel', 'ts'];

    /** @return BelongsTo<DailyDeparture, $this> */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(DailyDeparture::class, 'daily_departure_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
