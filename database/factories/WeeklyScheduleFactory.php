<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepartureMethod;
use App\Models\Child;
use App\Models\WeeklySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklySchedule>
 */
class WeeklyScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'child_id' => Child::factory(),
            'weekday' => fake()->numberBetween(1, 5),
            'planned_time' => fake()->randomElement(['14:00', '15:00', '16:00', '16:30']),
            'method' => fake()->randomElement(DepartureMethod::cases()),
        ];
    }
}
