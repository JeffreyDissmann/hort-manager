<?php

namespace Database\Seeders;

use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Frau Müller',
            'email' => 'erzieher@hort.test',
            'role' => UserRole::Staff,
        ]);

        $parent = User::factory()->create([
            'name' => 'Familie Schmidt',
            'email' => 'eltern@hort.test',
            'role' => UserRole::Parent,
        ]);

        // A handful of children with realistic weekly plans.
        $plans = [
            'Emma' => [1 => '16:00', 2 => '16:00', 3 => '15:00', 4 => '16:00', 5 => '14:30'],
            'Liam' => [1 => '15:30', 2 => null, 3 => '15:30', 4 => null, 5 => '15:30'],
            'Mia' => [1 => '16:30', 2 => '16:30', 3 => '16:30', 4 => '16:30', 5 => '16:30'],
            'Noah' => [1 => '14:00', 2 => '14:00', 3 => '14:00', 4 => '14:00', 5 => '14:00'],
            'Sophia' => [1 => null, 2 => '16:00', 3 => '16:00', 4 => '16:00', 5 => null],
        ];

        $children = [];

        foreach ($plans as $name => $week) {
            $child = Child::factory()->create(['name' => $name]);
            $children[$name] = $child;

            foreach ($week as $weekday => $time) {
                if ($time === null) {
                    continue;
                }

                $child->weeklySchedules()->create([
                    'weekday' => $weekday,
                    'planned_time' => $time,
                    // Fridays everyone goes home alone; otherwise they're picked up.
                    'method' => $weekday === 5 ? DepartureMethod::SentHome : DepartureMethod::PickedUp,
                ]);
            }
        }

        // Link the demo parent to two of the children.
        $parent->children()->sync([
            $children['Emma']->id,
            $children['Mia']->id,
        ]);
    }
}
