<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepartureMethod;
use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Child>
 */
class ChildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'date_of_birth' => fake()->dateTimeBetween('-8 years', '-5 years')->format('Y-m-d'),
            'note' => null,
            // Enrolled long ago, open-ended → active on any test date.
            'active_from' => '2020-01-01',
            'active_until' => null,
        ];
    }

    /** A child who has already left the Hort (active_until in the past). */
    public function former(string $leftOn = '2024-12-31'): static
    {
        return $this->state(['active_from' => '2020-01-01', 'active_until' => $leftOn]);
    }

    /** Give the child a Stammplan entry for a weekday (1–5); other days stay „Hortfrei". */
    public function scheduledOn(int $weekday, string $time = '15:00', DepartureMethod $method = DepartureMethod::PickedUp): static
    {
        return $this->afterCreating(fn (Child $child) => $child->weeklySchedules()->create([
            'weekday' => $weekday,
            'planned_time' => $time,
            'method' => $method,
        ]));
    }

    /** Link a guardian (parent) to the child. */
    public function withGuardian(User $guardian): static
    {
        return $this->afterCreating(fn (Child $child) => $child->guardians()->syncWithoutDetaching($guardian));
    }
}
