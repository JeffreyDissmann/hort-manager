<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\HolidayPeriod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Rejects an Ausflug date that falls into a Ferien-Zeitraum, naming it in the error.
 *
 * A **Schließzeit** is obvious: there is no Hort day to go anywhere from.
 *
 * A **Ferienbetreuung** is the less obvious half. Everyone who is there that day is
 * there all day — an outing during the holidays takes the whole group by definition,
 * so there is nobody to invite and nothing to answer. That outing is the day's
 * Aktivität in the Tagesprogramm, not a poll with its own participants.
 */
class NotDuringHolidayPeriod implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $period = HolidayPeriod::query()
            ->covering(Carbon::parse((string) $value))
            ->first();

        if ($period === null) {
            return;
        }

        $fail(__(
            $period->isCare() ? 'validation.care_day' : 'validation.closed_day',
            ['name' => $period->name],
        ));
    }
}
