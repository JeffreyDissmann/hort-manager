<?php

namespace App\Models;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Observers\DailyDepartureObserver;
use Database\Factories\DailyDepartureFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([DailyDepartureObserver::class])]
class DailyDeparture extends Model
{
    /** @use HasFactory<DailyDepartureFactory> */
    use HasFactory;

    protected $fillable = [
        'child_id',
        'date',
        'status',
        'planned_time',
        'planned_method',
        'left_at',
        'marked_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => DepartureStatus::class,
            'planned_method' => DepartureMethod::class,
            'left_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Child, $this>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * The staff member who recorded the departure.
     *
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
