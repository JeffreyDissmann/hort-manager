<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Hort-wide key/value settings. Reads are cached forever and busted on write,
 * so hot paths (every plan change) don't hit the database.
 */
class Setting extends Model
{
    /** The Hort-wide cutoff after which same-day changes notify staff. */
    public const LateChangeCutoff = 'late_change_cutoff';

    public const DefaultLateChangeCutoff = '12:00';

    /** When the Monday „Wochenüberblick" goes out to parents. */
    public const WeeklyDigestTime = 'weekly_digest_time';

    public const DefaultWeeklyDigestTime = '12:00';

    /** How long before the digest staff are reminded about an unfilled week. */
    public const ProgramReminderLeadMinutes = 30;

    /** Default Betreuungszeit for a new Ferienbetreuung day (staff override per day). */
    public const CareDefaultStart = 'care_default_start';

    public const CareDefaultEnd = 'care_default_end';

    public const DefaultCareStart = '08:30';

    public const DefaultCareEnd = '16:00';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Read a setting, falling back to `$default` when it was never set.
     *
     * Settings are read while registering the schedule (routes/console.php), i.e. on
     * every console boot — including `migrate` on a fresh install, before this table
     * exists. A missing table therefore falls back instead of breaking the command.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = Cache::rememberForever(self::cacheKey($key), fn () => self::query()->find($key)?->value);
        } catch (QueryException) {
            return $default;
        }

        return $value ?? $default;
    }

    /** Write a setting and bust its cache entry. */
    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::cacheKey($key));
    }

    /** The Hort-wide „späte Änderung" cutoff as `H:i`. */
    public static function lateChangeCutoff(): string
    {
        return (string) self::get(self::LateChangeCutoff, self::DefaultLateChangeCutoff);
    }

    /** When the Monday digest goes out to parents, as `H:i`. */
    public static function weeklyDigestTime(): string
    {
        return (string) self::get(self::WeeklyDigestTime, self::DefaultWeeklyDigestTime);
    }

    /**
     * When staff are reminded about an unfilled week program, as `H:i` — always
     * {@see self::ProgramReminderLeadMinutes} before the parent digest, so there is a
     * window left to fill it in.
     */
    public static function programReminderTime(): string
    {
        return Carbon::createFromFormat('H:i', self::weeklyDigestTime())
            ->subMinutes(self::ProgramReminderLeadMinutes)
            ->format('H:i');
    }

    /**
     * The Betreuungszeit a newly offered Ferienbetreuung day starts from, as `H:i`.
     *
     * @return array{0: string, 1: string} [start, end]
     */
    public static function careDefaultWindow(): array
    {
        return [
            (string) self::get(self::CareDefaultStart, self::DefaultCareStart),
            (string) self::get(self::CareDefaultEnd, self::DefaultCareEnd),
        ];
    }

    private static function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }
}
