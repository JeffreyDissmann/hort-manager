<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Child;
use App\Notifications\ScheduleMissingReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class RemindMissingSchedules extends Command
{
    protected $signature = 'wochenplan:remind-unset {--dry-run : List who would be reminded without sending anything}';

    protected $description = 'DM the guardians (Slack and/or push) of every child whose Stammplan isn\'t set up yet.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $reminded = 0;

        Child::withoutSchedule()->with('guardians')->orderBy('name')->get()->each(function (Child $child) use ($dryRun, &$reminded) {
            $guardians = $child->guardians()->reachable()->get();

            if ($guardians->isEmpty()) {
                if ($dryRun) {
                    $this->line("• {$child->name}: no reachable guardian (skipped)");
                }

                return;
            }

            if ($dryRun) {
                $this->line("• {$child->name} → ".$guardians->pluck('name')->join(', '));
            } else {
                Notification::send($guardians, new ScheduleMissingReminder($child));
            }

            $reminded += $guardians->count();
        });

        $this->info($dryRun
            ? "Dry run: would remind {$reminded} guardian(s) about a missing Wochenplan."
            : "Reminded {$reminded} guardian(s) about a missing Wochenplan.");

        return self::SUCCESS;
    }
}
