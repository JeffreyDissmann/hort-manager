<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\TimeQualifier;
use App\Observers\DailyDepartureObserver;
use Database\Factories\DailyDepartureFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
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
        'time_qualifier',
        'planned_method',
        'companion_child_id',
        'companion_confirmed',
        'companion_confirmed_by',
        'companion_confirmed_at',
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
            'time_qualifier' => TimeQualifier::class,
            'companion_confirmed' => 'boolean',
            'companion_confirmed_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /**
     * True while this „geht mit einem anderen Kind mit" pickup is still waiting for
     * the companion's family to confirm (only set when the companion goes home alone).
     */
    public function awaitingCompanionConfirmation(): bool
    {
        return $this->planned_method === DepartureMethod::WithChild
            && $this->companion_child_id !== null
            && $this->companion_confirmed === null;
    }

    /**
     * „Geht mit … mit" arrangements still awaiting the companion family's answer.
     *
     * @param  Builder<DailyDeparture>  $query
     */
    public function scopePendingCompanion(Builder $query): void
    {
        $query->where('planned_method', DepartureMethod::WithChild)
            ->whereNull('companion_confirmed');
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

    /**
     * The other child this pickup tags along with (for the `with_child` method).
     *
     * @return BelongsTo<Child, $this>
     */
    public function companion(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'companion_child_id');
    }

    /**
     * Who confirmed (or declined) the companion arrangement — the companion's
     * guardian or a staff member.
     *
     * @return BelongsTo<User, $this>
     */
    public function companionConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'companion_confirmed_by');
    }
}
