<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Accounting\Account;
use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
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
            'is_admin' => true,
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

        // A couple of Stammplan comments explaining the time.
        $children['Liam']->weeklySchedules()->where('weekday', 1)->update(['comment' => 'Fußballtraining']);
        $children['Emma']->weeklySchedules()->where('weekday', 3)->update(['comment' => 'früher wegen Schwimmkurs']);

        // Demo birthdays: Emma turns today, Noah on a weekday of the current week.
        $children['Emma']->update(['date_of_birth' => now()->subYears(7)->toDateString()]);
        $children['Noah']->update([
            'date_of_birth' => now()->startOfWeek()->addDays(2)->subYears(8)->toDateString(),
        ]);

        // Link the demo parent to two of the children.
        $parent->children()->sync([
            $children['Emma']->id,
            $children['Mia']->id,
        ]);

        $allChildIds = collect($children)->map->id->values()->all();

        // A demo excursion on the next Hort day (so it shows on the Tagesboard).
        // Poll already closed; Mia and Liam confirmed, so they are the participants.
        $excursionDate = now();
        while ($excursionDate->isWeekend()) {
            $excursionDate->addDay();
        }

        $zoo = Excursion::create([
            'name' => 'Zoo-Ausflug',
            'date' => $excursionDate->toDateString(),
            'depart_at' => '13:30',
            'return_at' => '15:30',
            'rsvp_deadline' => now()->subDay()->toDateString(),
            'note' => 'Brotzeit und feste Schuhe mitbringen.',
        ]);
        $zoo->children()->attach($allChildIds); // everyone invited (open)
        $zoo->children()->syncWithoutDetaching([
            $children['Mia']->id => ['response' => true, 'answered_at' => now()],
            $children['Liam']->id => ['response' => true, 'answered_at' => now()],
        ]);

        // An upcoming excursion whose poll is still open — parents get a notification.
        $schwimmbad = Excursion::create([
            'name' => 'Schwimmbad',
            'date' => now()->addDays(10)->toDateString(),
            'depart_at' => '13:00',
            'return_at' => '16:00',
            'rsvp_deadline' => now()->addDays(5)->toDateString(),
            'note' => 'Schwimmsachen und Handtuch mitbringen.',
        ]);
        $schwimmbad->children()->attach($allChildIds);

        // A past excursion for the history section.
        $waldtag = Excursion::create([
            'name' => 'Waldtag',
            'date' => now()->subDays(7)->toDateString(),
            'depart_at' => '13:00',
            'return_at' => '15:00',
            'rsvp_deadline' => now()->subDays(9)->toDateString(),
            'note' => 'Wetterfeste Kleidung.',
        ]);
        $waldtag->children()->attach($allChildIds);
        $waldtag->children()->syncWithoutDetaching([
            $children['Emma']->id => ['response' => true, 'answered_at' => now()->subDays(10)],
            $children['Noah']->id => ['response' => true, 'answered_at' => now()->subDays(10)],
            $children['Sophia']->id => ['response' => false, 'answered_at' => now()->subDays(10)],
        ]);

        // Day program for the current Mon–Fri week.
        $menus = [
            ['Nudeln mit Tomatensoße', 'Basteln mit Naturmaterialien'],
            ['Kartoffelsuppe', 'Turnhalle'],
            ['Reis mit Gemüsecurry', 'Ausflug zum Spielplatz'],
            ['Pfannkuchen', 'Freispiel im Garten'],
            ['Fischstäbchen mit Kartoffeln', 'Vorlesestunde'],
        ];
        $weekStart = now()->startOfWeek();
        foreach ($menus as $i => [$lunch, $activity]) {
            DailyProgram::create([
                'date' => $weekStart->copy()->addDays($i)->toDateString(),
                'lunch' => $lunch,
                'activity' => $activity,
            ]);
        }

        // Default homework slot: 14:00–15:00 Mon–Thu, shorter on Friday.
        foreach (range(1, 5) as $weekday) {
            HomeworkDefault::create([
                'weekday' => $weekday,
                'start_time' => '14:00',
                'end_time' => $weekday === 5 ? '14:30' : '15:00',
            ]);
        }

        // Buchhaltung accounts: the Hort's bank account and its cash box.
        Account::create([
            'name' => 'Hort-Konto',
            'iban' => 'DE89370400440532013000',
            'opening_balance_cents' => 250000,
            'opening_balance_date' => now()->startOfYear()->toDateString(),
        ]);
        Account::create([
            'name' => 'Bar-Kasse',
            'opening_balance_cents' => 15000,
            'opening_balance_date' => now()->startOfYear()->toDateString(),
        ]);
    }
}
