<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DepartureMethod;
use App\Enums\HolidayPeriodType;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

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

        $this->seedHolidays();
    }

    /**
     * One of each Ferien kind, in the near future: a Schließzeit (Hort shut) and a
     * Ferienbetreuung whose days are open for sign-up.
     */
    private function seedHolidays(): void
    {
        $monday = Carbon::today()->addWeeks(3)->startOfWeek(Carbon::MONDAY);

        HolidayPeriod::updateOrCreate(
            ['name' => 'Brückentag (Demo)'],
            [
                'type' => HolidayPeriodType::Closed,
                'starts_on' => $monday->copy()->addDays(4)->toDateString(), // Friday
                'ends_on' => $monday->copy()->addDays(4)->toDateString(),
                'note' => 'Team-Fortbildung',
            ],
        );

        $care = HolidayPeriod::updateOrCreate(
            ['name' => 'Ferienbetreuung (Demo)'],
            [
                'type' => HolidayPeriodType::Care,
                'starts_on' => $monday->copy()->addWeek()->toDateString(),
                'ends_on' => $monday->copy()->addWeek()->addDays(4)->toDateString(),
                // Still open, so the sign-up screen is usable.
                'registration_deadline' => Carbon::today()->addWeek()->toDateString(),
            ],
        );

        $care->generateCareDays();

        // One day with shorter hours, to show the per-day override.
        $care->careDays()->orderBy('date')->skip(2)->first()?->update([
            'starts_at' => '09:00',
            'ends_at' => '15:00',
        ]);

        // A second one whose Anmeldeschluss is *today*, so `care:remind-open` has
        // something to chase — the reminder fires on the deadline day, like an Ausflug.
        $due = HolidayPeriod::updateOrCreate(
            ['name' => 'Herbst-Ferienbetreuung (Demo)'],
            [
                'type' => HolidayPeriodType::Care,
                'starts_on' => $monday->copy()->addWeeks(4)->toDateString(),
                'ends_on' => $monday->copy()->addWeeks(4)->addDays(4)->toDateString(),
                'registration_deadline' => Carbon::today()->toDateString(),
            ],
        );

        // Note: demo parents deliberately get no slack_id. „Reachable" means Slack or
        // push, and a made-up id fails the real Slack API with channel_not_found — to
        // try the reminders, enable push on a device or use a real Slack account.
        $due->generateCareDays();
    }
}
