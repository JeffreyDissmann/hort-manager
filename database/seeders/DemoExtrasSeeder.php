<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Non-destructive extras that showcase the newer Stammplan features — „Hortfrei"
 * weekdays, the bis/genau um/ab time qualifier, and a child with no plan yet (for
 * the „Wochenplan fehlt" banner). Safe to run on an existing database and idempotent
 * (re-running just refreshes these three demo children). Run:
 *
 *   sail artisan db:seed --class=DemoExtrasSeeder
 */
class DemoExtrasSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $parent = User::firstOrCreate(
            ['email' => 'eltern@hort.test'],
            ['name' => 'Familie Schmidt', 'role' => UserRole::Parent],
        );

        // [weekday => [time, method, qualifier?, comment?]]; missing weekdays = „Hortfrei".
        $demo = [
            'Ben (Demo)' => [
                1 => ['16:00', DepartureMethod::PickedUp],
                2 => ['16:00', DepartureMethod::PickedUp],
                3 => ['15:00', DepartureMethod::SentHome, TimeQualifier::By, 'wegen Sport'], // „bis 15:00"
                4 => ['16:00', DepartureMethod::PickedUp],
                // Freitag: kein Eintrag → Hortfrei
            ],
            'Lena (Demo)' => [
                2 => ['16:30', DepartureMethod::PickedUp],
                4 => ['16:00', DepartureMethod::SentHome, TimeQualifier::From], // „ab 16:00"
                // Mo/Mi/Fr: Hortfrei
            ],
            // Kein Stammplan → «Wochenplan fehlt»-Hinweis für die Eltern.
            'Max (Demo)' => [],
        ];

        foreach ($demo as $name => $week) {
            $child = Child::firstOrCreate(['name' => $name]);
            $child->weeklySchedules()->delete(); // refresh on re-run

            foreach ($week as $weekday => $row) {
                [$time, $method, $qualifier, $comment] = array_pad($row, 4, null);
                $child->weeklySchedules()->create([
                    'weekday' => $weekday,
                    'planned_time' => $time,
                    'method' => $method,
                    'time_qualifier' => $method === DepartureMethod::SentHome ? $qualifier : null,
                    'comment' => $comment,
                ]);
            }

            $parent->children()->syncWithoutDetaching($child->id);
        }
    }
}
