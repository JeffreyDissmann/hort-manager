<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a Hort-wide holiday period means. `Closed` shuts the Hort completely;
 * `Care` (Ferienbetreuung) is the opt-in programme — not implemented yet, so
 * nothing creates one, but the column exists so adding it needs no migration.
 */
enum HolidayPeriodType: string
{
    case Closed = 'closed';
    case Care = 'care';

    /** Localised label for the UI (de/en, per the active locale). */
    public function label(): string
    {
        return __('enums.holiday_period_type.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }
}
