<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\HolidayPeriod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/** Rejects a date on which the Hort is closed, naming the Schließzeit in the error. */
class NotDuringClosure implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $period = HolidayPeriod::query()
            ->closed()
            ->covering(Carbon::parse((string) $value))
            ->first();

        if ($period) {
            $fail(__('validation.closed_day', ['name' => $period->name]));
        }
    }
}
