<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Observers\ChildObserver;
use Database\Factories\ChildFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ChildObserver::class])]
class Child extends Model
{
    /** @use HasFactory<ChildFactory> */
    use HasFactory, LogsChanges;

    /** @return list<string> */
    protected function activityAttributes(): array
    {
        return ['name', 'date_of_birth', 'note', 'active_from', 'active_until'];
    }

    protected function activityLabel(): string
    {
        return $this->name;
    }

    protected $fillable = [
        'name',
        'date_of_birth',
        'note',
        'active_from',
        'active_until',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'active_from' => 'date:Y-m-d',
            'active_until' => 'date:Y-m-d',
        ];
    }

    /**
     * Enrolled on the given date (accepts a Carbon/DateTime or a Y-m-d string):
     * started on/before it and not yet left (a null active_from is treated as
     * "always enrolled" for safety).
     *
     * @param  Builder<Child>  $query
     */
    public function scopeActiveOn(Builder $query, DateTimeInterface|string $date): void
    {
        $on = self::toDateString($date);

        $query
            ->where(fn (Builder $q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('active_until')->orWhereDate('active_until', '>=', $on));
    }

    /**
     * Enrolled at any point within the date range (inclusive overlap).
     *
     * @param  Builder<Child>  $query
     */
    public function scopeActiveBetween(Builder $query, DateTimeInterface|string $from, DateTimeInterface|string $to): void
    {
        $from = self::toDateString($from);
        $to = self::toDateString($to);

        $query
            ->where(fn (Builder $q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', $to))
            ->where(fn (Builder $q) => $q->whereNull('active_until')->orWhereDate('active_until', '>=', $from));
    }

    /**
     * Enrolled at any point during the calendar year (inclusive overlap).
     *
     * @param  Builder<Child>  $query
     */
    public function scopeActiveInYear(Builder $query, int $year): void
    {
        $query->activeBetween("{$year}-01-01", "{$year}-12-31");
    }

    /** Whether the child is enrolled on the given date (Carbon/DateTime or Y-m-d string). */
    public function isActiveOn(DateTimeInterface|string $date): bool
    {
        $on = self::toDateString($date);

        return ($this->active_from === null || $this->active_from->toDateString() <= $on)
            && ($this->active_until === null || $this->active_until->toDateString() >= $on);
    }

    private static function toDateString(DateTimeInterface|string $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }

    /**
     * The child's weekly default plan (Stammplan), one row per weekday (1–5).
     *
     * @return HasMany<WeeklySchedule, $this>
     */
    public function weeklySchedules(): HasMany
    {
        return $this->hasMany(WeeklySchedule::class);
    }

    /**
     * Children whose Stammplan hasn't been set up yet (no weekday entries at all),
     * so their Wochenplan is still empty.
     *
     * @param  Builder<Child>  $query
     */
    public function scopeWithoutSchedule(Builder $query): void
    {
        $query->whereDoesntHave('weeklySchedules');
    }

    /** @return HasMany<Absence, $this> */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    /**
     * Departures of *other* children set to go home with this one („geht mit … mit").
     *
     * @return HasMany<DailyDeparture, $this>
     */
    public function accompaniedDepartures(): HasMany
    {
        return $this->hasMany(DailyDeparture::class, 'companion_child_id');
    }

    /**
     * The parent users (Eltern) linked to this child.
     *
     * @return BelongsToMany<User, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** Whether the given user is a parent of this child. */
    public function isGuardedBy(User $user): bool
    {
        return $this->guardians()->whereKey($user->getKey())->exists();
    }

    /**
     * @return BelongsToMany<Excursion, $this>
     */
    public function excursions(): BelongsToMany
    {
        return $this->belongsToMany(Excursion::class);
    }
}
