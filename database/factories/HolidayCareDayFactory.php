<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<HolidayCareDay>
 */
class HolidayCareDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'holiday_period_id' => HolidayPeriod::factory()->care(),
            'date' => Carbon::today()->addWeeks(2)->startOfWeek(Carbon::MONDAY)->toDateString(),
            'starts_at' => '08:30',
            'ends_at' => '16:30',
        ];
    }

    public function on(Carbon|string $date): static
    {
        return $this->state(['date' => $date instanceof Carbon ? $date->toDateString() : $date]);
    }
}
