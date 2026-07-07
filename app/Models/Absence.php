<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AbsenceReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A child is away (sick or otherwise) on a given date — no pickup expected. */
class Absence extends Model
{
    protected $fillable = ['child_id', 'date', 'reason', 'comment', 'reported_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'reason' => AbsenceReason::class,
        ];
    }

    /**
     * Record a child as away on a date and drop any not-yet-departed pickup.
     */
    public static function report(Child $child, string $date, AbsenceReason $reason, ?int $reportedBy, ?string $comment = null): self
    {
        $absence = static::updateOrCreate(
            ['child_id' => $child->id, 'date' => $date],
            ['reason' => $reason, 'comment' => $comment, 'reported_by' => $reportedBy],
        );

        DailyDeparture::where('child_id', $child->id)
            ->where('date', $date)
            ->whereNull('left_at')
            ->delete();

        return $absence;
    }

    /** @return BelongsTo<Child, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
