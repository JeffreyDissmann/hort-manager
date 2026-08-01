<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HolidayPeriodType;
use App\Models\Concerns\LogsChanges;
use Database\Factories\HolidayPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A named Hort-wide date range (Ferien, Brückentag, Fortbildung). A `closed` period
 * means the Hort doesn't exist on those days: no board, no plan, no Tagesprogramm —
 * which is different from „Hortfrei" (per child, structural) and from an Absence
 * (per child, reported with a reason). Nobody is absent; there is no day.
 */
class HolidayPeriod extends Model
{
    /** @use HasFactory<HolidayPeriodFactory> */
    use HasFactory, LogsChanges;

    protected $fillable = [
        'name',
        'type',
        'starts_on',
        'ends_on',
        'note',
    ];

    protected $attributes = [
        'type' => HolidayPeriodType::Closed->value,
    ];

    protected function casts(): array
    {
        return [
            'type' => HolidayPeriodType::class,
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }

    /** @return list<string> */
    protected function activityAttributes(): array
    {
        return ['name', 'type', 'starts_on', 'ends_on', 'note'];
    }

    protected function activityLabel(): string
    {
        return $this->name;
    }

    /**
     * Periods that shut the Hort completely.
     *
     * @param  Builder<HolidayPeriod>  $query
     */
    public function scopeClosed(Builder $query): void
    {
        $query->where('type', HolidayPeriodType::Closed);
    }

    /**
     * Periods covering the given day.
     *
     * @param  Builder<HolidayPeriod>  $query
     */
    public function scopeCovering(Builder $query, Carbon|string $date): void
    {
        $day = $date instanceof Carbon ? $date->toDateString() : $date;

        $query->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day);
    }

    /**
     * Periods overlapping the given range at any point (inclusive).
     *
     * @param  Builder<HolidayPeriod>  $query
     */
    public function scopeOverlapping(Builder $query, Carbon|string $from, Carbon|string $to): void
    {
        $start = $from instanceof Carbon ? $from->toDateString() : $from;
        $end = $to instanceof Carbon ? $to->toDateString() : $to;

        $query->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start);
    }

    /**
     * Every date of this period, inclusive, as `Y-m-d`.
     *
     * @return Collection<int, string>
     */
    public function days(): Collection
    {
        return collect($this->starts_on->toPeriod($this->ends_on)->toArray())
            ->map(fn (Carbon $day): string => $day->toDateString());
    }

    /** How many days the period spans, inclusive. */
    public function dayCount(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }
}
