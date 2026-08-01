<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HolidayPeriodType;
use App\Models\HolidayPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<HolidayPeriod>
 */
class HolidayPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::today()->addWeeks(2)->startOfWeek(Carbon::MONDAY);

        return [
            'name' => 'Sommerferien',
            'type' => HolidayPeriodType::Closed,
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addDays(4)->toDateString(),
            'note' => null,
        ];
    }

    /** A Ferienbetreuung (children opt in per day) rather than a closure. */
    public function care(): static
    {
        return $this->state([
            'name' => 'Ferienbetreuung',
            'type' => HolidayPeriodType::Care,
        ]);
    }

    /** A single closed day (Brückentag, Fortbildung). */
    public function onDay(Carbon|string $date): static
    {
        $day = $date instanceof Carbon ? $date->toDateString() : $date;

        return $this->state(['starts_on' => $day, 'ends_on' => $day]);
    }

    /** A period spanning the given range. */
    public function between(Carbon|string $from, Carbon|string $to): static
    {
        return $this->state([
            'starts_on' => $from instanceof Carbon ? $from->toDateString() : $from,
            'ends_on' => $to instanceof Carbon ? $to->toDateString() : $to,
        ]);
    }
}
