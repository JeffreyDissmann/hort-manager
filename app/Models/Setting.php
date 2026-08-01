<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    /** Read a setting, falling back to `$default` when it was never set. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(self::cacheKey($key), fn () => self::query()->find($key)?->value);

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

    private static function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }
}
