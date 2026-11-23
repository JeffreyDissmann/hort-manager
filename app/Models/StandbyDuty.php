<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Support\StandbyRoster;
use Database\Factories\StandbyDutyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One filled Springerdienst slot: on `date`, the guardians of `child` are on call to
 * cover when Erzieher are ill. The calling itself happens outside the app — this is
 * only the list. How many slots a date has is {@see StandbyRoster::slotsBetween()}.
 */
class StandbyDuty extends Model
{
    /** @use HasFactory<StandbyDutyFactory> */
    use HasFactory, LogsChanges;

    protected $fillable = [
        'date',
        'slot',
        'child_id',
        'note',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'slot' => 'integer',
        ];
    }

    /** @return list<string> */
    protected function activityAttributes(): array
    {
        return ['date', 'slot', 'child_id', 'note'];
    }

    protected function activityLabel(): string
    {
        return 'Springerdienst '.$this->date?->format('d.m.Y');
    }

    /** @return BelongsTo<Child, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Duties in the given range (inclusive).
     *
     * @param  Builder<StandbyDuty>  $query
     */
    public function scopeBetween(Builder $query, Carbon|string $from, Carbon|string $to): void
    {
        $query->whereBetween('date', [
            $from instanceof Carbon ? $from->toDateString() : $from,
            $to instanceof Carbon ? $to->toDateString() : $to,
        ]);
    }

    /**
     * The Kita year a date belongs to, as its first and last day (1 Sept – 31 Aug) —
     * the period the fair fill balances over.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function schoolYearOf(Carbon|string $date): array
    {
        $day = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $startYear = $day->month >= 9 ? $day->year : $day->year - 1;

        return [
            Carbon::create($startYear, 9, 1)->startOfDay(),
            Carbon::create($startYear + 1, 8, 31)->startOfDay(),
        ];
    }
}
