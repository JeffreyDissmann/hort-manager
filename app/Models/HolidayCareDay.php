<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Observers\HolidayCareDayObserver;
use Database\Factories\HolidayCareDayFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One offered day of a Ferienbetreuung: that it runs, and between when. Created with
 * the Hort-wide default times when the period is set up, then adjusted per day by
 * staff. Children attend only if they signed up for this day.
 *
 * Deliberately holds no Aktivität or Essen — that is DailyProgram's job on every
 * other Hort day, and a care day is no different.
 *
 * Soft-deleted so „staff stopped offering this day" leaves a trace: re-saving the
 * period fills gaps in its range, and without the tombstone it would put a
 * deliberately removed day straight back on the sign-up sheet.
 */
#[ObservedBy([HolidayCareDayObserver::class])]
class HolidayCareDay extends Model
{
    /** @use HasFactory<HolidayCareDayFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

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
     * attending is planned exactly like any other Hort day. Keyed off the care day and
     * not the date: a plan override that happens to fall on an offered date is not a
     * sign-up, and counting it as one would put an unregistered child on the roster.
     *
     * @return HasMany<DailyDeparture, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(DailyDeparture::class, 'holiday_care_day_id');
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

    /**
     * The offered days in a range, keyed by `Y-m-d`. Every date-anchored view needs
     * this to tell a Ferienbetreuung day from a normal one — most visibly to drop the
     * homework band, which otherwise follows the per-weekday default into the holidays.
     *
     * @return Collection<string, HolidayCareDay>
     */
    public static function betweenKeyed(Carbon|string $from, Carbon|string $to): Collection
    {
        return static::query()
            // The deadline travels with the day: the Wochenplan tells an unregistered
            // family whether signing up is still possible.
            ->with('period:id,name,registration_deadline')
            ->whereBetween('date', [
                $from instanceof Carbon ? $from->toDateString() : $from,
                $to instanceof Carbon ? $to->toDateString() : $to,
            ])
            ->get()
            ->keyBy(fn (self $day): string => $day->date->toDateString());
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
