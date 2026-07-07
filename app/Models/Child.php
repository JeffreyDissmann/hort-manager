<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Child extends Model
{
    /** @use HasFactory<ChildFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'date_of_birth',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
        ];
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
