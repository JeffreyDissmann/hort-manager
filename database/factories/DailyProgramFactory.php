<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DailyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyProgram>
 */
class DailyProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'lunch' => fake()->randomElement(['Nudeln mit Tomatensoße', 'Kartoffelsuppe', 'Reis mit Gemüse', 'Pfannkuchen']),
            'activity' => fake()->randomElement(['Basteln', 'Ausflug in den Park', 'Sport in der Turnhalle', 'Freispiel']),
        ];
    }
}
