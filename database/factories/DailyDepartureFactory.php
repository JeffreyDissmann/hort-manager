<?php

namespace Database\Factories;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Models\Child;
use App\Models\DailyDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyDeparture>
 */
class DailyDepartureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'child_id' => Child::factory(),
            'date' => now()->toDateString(),
            'status' => DepartureStatus::Present,
            'planned_time' => fake()->randomElement(['14:00', '15:00', '16:00']),
            'planned_method' => fake()->randomElement(DepartureMethod::cases()),
            'left_at' => null,
            'marked_by' => null,
            'note' => null,
        ];
    }
}
