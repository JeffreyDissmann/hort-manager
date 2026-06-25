<?php

namespace App\Models;

use App\Enums\DepartureMethod;
use Database\Factories\WeeklyScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklySchedule extends Model
{
    /** @use HasFactory<WeeklyScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'child_id',
        'weekday',
        'planned_time',
        'method',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'method' => DepartureMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Child, $this>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
