<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\DailyProgram;
use App\Models\User;
use App\Notifications\ProgramMissing;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class RemindMissingProgram extends Command
{
    protected $signature = 'program:remind-missing {--dry-run : List what would be sent without sending anything}';

    protected $description = 'DM staff (Slack and/or push) when this week\'s Tagesprogramm still has days without lunch.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $missing = DailyProgram::weekdaysWithoutLunch(Carbon::today());

        if ($missing === []) {
            $this->info('The week program is complete — nothing to remind about.');

            return self::SUCCESS;
        }

        $days = collect($missing)->map(fn (Carbon $d): string => $d->toDateString())->join(', ');

        // Staff who still want this category on at least one channel.
        $recipients = User::query()
            ->reachable()
            ->where('role', UserRole::Staff)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->wantsNotification(NotificationCategory::ProgramMissing->value, 'slack')
                || $user->wantsNotification(NotificationCategory::ProgramMissing->value, 'push'));

        if ($dryRun) {
            $this->line("Missing lunch: {$days}");
            $recipients->each(fn (User $user) => $this->line("• {$user->name}"));
            $this->info("Dry run: would remind {$recipients->count()} staff member(s).");

            return self::SUCCESS;
        }

        Notification::send($recipients, new ProgramMissing($missing));

        $this->info("Reminded {$recipients->count()} staff member(s) about {$days}.");

        return self::SUCCESS;
    }
}
