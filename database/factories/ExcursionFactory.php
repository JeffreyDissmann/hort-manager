<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Excursion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Excursion>
 */
class ExcursionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Zoo-Ausflug', 'Schwimmbad', 'Waldtag', 'Museum', 'Spielplatz']),
            'date' => now()->toDateString(),
            'depart_at' => '09:00',
            'return_at' => '15:00',
            'note' => null,
        ];
    }

    /** Upcoming trip whose RSVP poll is still open (deadline in the future). */
    public function pollOpen(): static
    {
        return $this->state(fn () => [
            'date' => now()->addWeek()->toDateString(),
            'rsvp_deadline' => now()->addDays(3)->toDateString(),
        ]);
    }
}
