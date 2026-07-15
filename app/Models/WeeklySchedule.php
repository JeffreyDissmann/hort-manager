<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use App\Models\Concerns\LogsChanges;
use Database\Factories\WeeklyScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklySchedule extends Model
{
    /** @use HasFactory<WeeklyScheduleFactory> */
    use HasFactory, LogsChanges;

    /** @return list<string> */
    protected function activityAttributes(): array
    {
        return ['weekday', 'planned_time', 'method', 'time_qualifier', 'comment'];
    }

    protected function activityLabel(): string
    {
        return $this->child?->name ?? '?';
    }

    protected $fillable = [
        'child_id',
        'weekday',
        'planned_time',
        'method',
        'time_qualifier',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'method' => DepartureMethod::class,
            'time_qualifier' => TimeQualifier::class,
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
