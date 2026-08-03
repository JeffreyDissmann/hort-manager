<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Wraps Spatie's LogsActivity with this app's conventions: log only the given
 * attributes, only when they actually change, and stamp each entry with a short
 * German subject label (baked at log time so it survives the subject being deleted).
 */
trait LogsChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->activityAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (): string => $this->activityLabel());
    }

    /**
     * Attributes whose changes are worth recording.
     *
     * @return list<string>
     */
    abstract protected function activityAttributes(): array;

    /** A short, human-readable label for the subject (e.g. „Kind „Emma""). */
    abstract protected function activityLabel(): string;
}
