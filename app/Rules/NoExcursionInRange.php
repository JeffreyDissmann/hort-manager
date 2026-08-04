<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Excursion;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Rejects a Ferien-Zeitraum that would swallow an Ausflug. A trip can't be booked
 * inside one (see {@see NotDuringHolidayPeriod}) — laying the period over the trip
 * afterwards is the same conflict from the other side, and the board would show the
 * Ferien card while the trip still chases RSVPs. Naming the trip lets staff move or
 * cancel it themselves, which is theirs to decide: silently deleting one would take
 * its answers with it.
 *
 * Applied to the range's *end*, so the message lands on one field with both bounds
 * known.
 */
class NoExcursionInRange implements ValidationRule
{
    public function __construct(private readonly mixed $from) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || blank($this->from)) {
            return;
        }

        try {
            $from = Carbon::parse((string) $this->from)->toDateString();
            $to = Carbon::parse((string) $value)->toDateString();
        } catch (\Exception) {
            return; // Not a date — the date rules report that.
        }

        $excursions = Excursion::query()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->pluck('name');

        if ($excursions->isNotEmpty()) {
            $fail(__('validation.excursion_in_range', ['names' => $excursions->implode(', ')]));
        }
    }
}
