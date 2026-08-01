<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\HolidayCareDayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One offered day of a Ferienbetreuung: that it runs, and between when. Created with
 * the Hort-wide default times when the period is set up, then adjusted per day by
 * staff. Children attend only if they signed up for this day.
 *
 * Deliberately holds no Aktivität or Essen — that is DailyProgram's job on every
 * other Hort day, and a care day is no different.
 */
class HolidayCareDay extends Model
{
    /** @use HasFactory<HolidayCareDayFactory> */
    use HasFactory, LogsChanges;

    protected $fillable = [
        'holiday_period_id',
        'date',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /** @return list<string> */
    protected function activityAttributes(): array
    {
        return ['date', 'starts_at', 'ends_at'];
    }

    protected function activityLabel(): string
    {
        return $this->date?->format('d.m.Y') ?? '?';
    }

    /** @return BelongsTo<HolidayPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(HolidayPeriod::class, 'holiday_period_id');
    }

    /**
     * The sign-ups for this day — a child's DailyDeparture *is* the registration, so
     * attending is planned exactly like any other Hort day.
     *
     * @return HasMany<DailyDeparture, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(DailyDeparture::class, 'date', 'date');
    }

    /**
     * Days on the given date.
     *
     * @param  Builder<HolidayCareDay>  $query
     */
    public function scopeOnDate(Builder $query, Carbon|string $date): void
    {
        $query->whereDate('date', $date instanceof Carbon ? $date->toDateString() : $date);
    }

    /** The care window as „08:30–16:30". */
    public function window(): string
    {
        return self::short($this->starts_at).'–'.self::short($this->ends_at);
    }

    public static function short(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
