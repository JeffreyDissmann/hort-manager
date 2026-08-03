<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Child;
use App\Models\HolidayCareAnswer;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Notifications\CareRegistrationReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Modelled on excursions:remind-rsvps — the Anmeldeschluss lives on the period, and
 * the reminder goes out on that day. A period without a deadline is never chased.
 */
class RemindCareRegistration extends Command
{
    protected $signature = 'care:remind-open {--dry-run : List who would be reminded without sending anything}';

    protected $description = 'Remind guardians (Slack and/or push) who still owe a Ferienbetreuung answer due today.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $reminded = 0;

        HolidayPeriod::dueToday()->get()->each(function (HolidayPeriod $period) use ($dryRun, &$reminded) {
            $answered = HolidayCareAnswer::query()
                ->where('holiday_period_id', $period->id)
                ->pluck('child_id');

            // Only children actually enrolled during the Ferienbetreuung; „keine Tage"
            // is an answer, so those families are already excluded above.
            $pending = Child::query()
                ->whereNotIn('id', $answered)
                ->activeBetween($period->starts_on, $period->ends_on)
                ->pluck('id');

            $guardians = User::query()
                ->reachable()
                ->whereHas('children', fn ($query) => $query->whereIn('children.id', $pending))
                ->orderBy('name')
                ->get();

            foreach ($guardians as $guardian) {
                // One DM per guardian, naming their own pending children.
                $names = $guardian->children()
                    ->whereIn('children.id', $pending)
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();

                if ($dryRun) {
                    $this->line("• {$guardian->name}: {$period->name} — ".implode(', ', $names));
                } else {
                    Notification::send([$guardian], new CareRegistrationReminder($period, $names));
                }

                $reminded++;
            }
        });

        $this->info($dryRun
            ? "Dry run: would remind {$reminded} guardian(s) about a Ferienbetreuung due today."
            : "Reminded {$reminded} guardian(s) about a Ferienbetreuung due today.");

        return self::SUCCESS;
    }
}
