<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\DailyProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// HomeworkDefault is in this namespace (App\Models); referenced by effectiveHomework().

class DailyProgram extends Model
{
    /** @use HasFactory<DailyProgramFactory> */
    use HasFactory, LogsChanges;

    /** @return list<string> */
    protected function activityAttributes(): array
    {
        return ['date', 'lunch', 'activity', 'homework_start', 'homework_end', 'homework_none'];
    }

    protected function activityLabel(): string
    {
        return $this->date?->format('d.m.Y') ?? '?';
    }

    protected $fillable = [
        'date',
        'lunch',
        'activity',
        'homework_start',
        'homework_end',
        'homework_none',
    ];

    protected $attributes = [
        'homework_none' => false,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'homework_none' => 'boolean',
        ];
    }

    /**
     * The weekdays (Mo–Fr) of the given week that still have no lunch entered.
     *
     * Only the lunch counts as „ausgefüllt" — an Aktivität is optional, so requiring
     * one would nag forever in weeks that simply don't have one every day.
     *
     * @return list<Carbon>
     */
    public static function weekdaysWithoutLunch(Carbon $weekStart): array
    {
        $monday = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $dates = collect(range(0, 4))->map(fn (int $i): string => $monday->copy()->addDays($i)->toDateString());

        // A day without lunch usually has no row at all (empty days are deleted), so
        // the missing days are the generated week minus what the table does have.
        $filled = static::query()
            ->whereIn('date', $dates)
            ->whereNotNull('lunch')
            ->where('lunch', '!=', '')
            ->pluck('date')
            ->map->toDateString();

        return $dates
            ->diff($filled)
            ->map(fn (string $date): Carbon => Carbon::parse($date))
            ->values()
            ->all();
    }

    /**
     * The effective homework slot for a day: the per-date override, otherwise the
     * weekday default — unless the day is explicitly marked "no homework".
     *
     * @return array{0: ?string, 1: ?string} [start, end]
     */
    public static function effectiveHomework(?self $program, ?HomeworkDefault $default): array
    {
        if ($program && $program->homework_none) {
            return [null, null];
        }

        return [
            $program?->homework_start ?? $default?->start_time,
            $program?->homework_end ?? $default?->end_time,
        ];
    }
}
