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
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([DailyDepartureObserver::class])]
class DailyDeparture extends Model
{
    /** @use HasFactory<DailyDepartureFactory> */
    use HasFactory;

    protected $fillable = [
        'child_id',
        'date',
        // Set when this row is a Ferienbetreuung sign-up rather than a plan override.
        'holiday_care_day_id',
        'status',
        'planned_time',
        'time_qualifier',
        // „Kommt später": optional arrival time + reason for this one day.
        'arrives_at',
        'arrival_note',
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
     * The offered Ferienbetreuung day this row signs the child up for, if any.
     *
     * @return BelongsTo<HolidayCareDay, $this>
     */
    public function careDay(): BelongsTo
    {
        return $this->belongsTo(HolidayCareDay::class, 'holiday_care_day_id');
    }

    /** Whether this row is a Ferienbetreuung sign-up rather than a plan override. */
    public function isCareRegistration(): bool
    {
        return $this->holiday_care_day_id !== null;
    }

    /** The „kommt später" arrival time as HH:MM, or null when the child arrives as usual. */
    public function arrivalTime(): ?string
    {
        return $this->arrives_at ? substr((string) $this->arrives_at, 0, 5) : null;
    }

    /**
     * One German line for a late arrival — „kommt erst um 14:30 (Arzttermin)" — shared
     * by the Späte-Änderung DM, the Wochenüberblick and the TRMNL feed. Null without a time.
     */
    public static function describeArrival(?string $time, ?string $note): ?string
    {
        if (! $time) {
            return null;
        }

        $note = trim((string) $note);

        return 'kommt erst um '.substr($time, 0, 5).($note !== '' ? " ({$note})" : '');
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
     * The per-guardian Slack DMs asking the companion's family to confirm.
     *
     * @return HasMany<CompanionSlackMessage, $this>
     */
    public function companionSlackMessages(): HasMany
    {
        return $this->hasMany(CompanionSlackMessage::class);
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
