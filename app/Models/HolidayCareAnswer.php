<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * „This family has answered for this Ferienbetreuung." Kept apart from the per-day
 * registrations because picking *no* days is a valid answer — without this marker the
 * deadline reminder couldn't tell „not coming" from „hasn't looked yet".
 */
class HolidayCareAnswer extends Model
{
    protected $fillable = [
        'holiday_period_id',
        'child_id',
        'answered_by',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<HolidayPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(HolidayPeriod::class, 'holiday_period_id');
    }

    /** @return BelongsTo<Child, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
