<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Child;
use App\Notifications\ScheduleMissingReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class RemindMissingSchedules extends Command
{
    protected $signature = 'wochenplan:remind-unset';

    protected $description = 'DM the guardians (Slack and/or push) of every child whose Stammplan isn\'t set up yet.';

    public function handle(): int
    {
        $reminded = 0;

        Child::withoutSchedule()->get()->each(function (Child $child) use (&$reminded) {
            $guardians = $child->guardians()->reachable()->get();

            if ($guardians->isNotEmpty()) {
                Notification::send($guardians, new ScheduleMissingReminder($child));
                $reminded += $guardians->count();
            }
        });

        $this->info("Reminded {$reminded} guardian(s) about a missing Wochenplan.");

        return self::SUCCESS;
    }
}
