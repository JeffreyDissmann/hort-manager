<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Child;
use App\Models\StandbyDuty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<StandbyDuty>
 */
class StandbyDutyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => Carbon::today()->nextWeekday()->toDateString(),
            'slot' => 1,
            'child_id' => Child::factory(),
            'note' => null,
            'assigned_by' => null,
        ];
    }

    /** On the given day (slot 1 unless stated). */
    public function onDay(Carbon|string $date, int $slot = 1): static
    {
        return $this->state([
            'date' => $date instanceof Carbon ? $date->toDateString() : $date,
            'slot' => $slot,
        ]);
    }
}
