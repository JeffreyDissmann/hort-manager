<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcursionSlackMessage extends Model
{
    protected $fillable = ['excursion_id', 'user_id', 'channel', 'ts'];

    /** @return BelongsTo<Excursion, $this> */
    public function excursion(): BelongsTo
    {
        return $this->belongsTo(Excursion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
