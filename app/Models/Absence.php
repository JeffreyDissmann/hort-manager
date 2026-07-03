<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AbsenceReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A child is away (sick or otherwise) on a given date — no pickup expected. */
class Absence extends Model
{
    protected $fillable = ['child_id', 'date', 'reason', 'reported_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'reason' => AbsenceReason::class,
        ];
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
