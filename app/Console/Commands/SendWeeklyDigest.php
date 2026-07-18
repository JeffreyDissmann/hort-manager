<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\WeeklyDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class SendWeeklyDigest extends Command
{
    protected $signature = 'weekly:digest {--dry-run : List who would receive the digest without sending anything}';

    protected $description = 'DM guardians (Slack and/or push) the weekly overview: food, activities and their child\'s week.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        // Reachable guardians who still want the digest on at least one channel.
        $recipients = User::query()
            ->reachable()
            ->guardians()
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->wantsNotification(NotificationCategory::WeeklyDigest->value, 'slack')
                || $user->wantsNotification(NotificationCategory::WeeklyDigest->value, 'push'));

        if ($dryRun) {
            $recipients->each(fn (User $user) => $this->line("• {$user->name}"));
            $this->info("Dry run: would send the weekly digest to {$recipients->count()} guardian(s).");

            return self::SUCCESS;
        }

        Notification::send($recipients, new WeeklyDigest($weekStart));

        $this->info("Sent the weekly digest to {$recipients->count()} guardian(s).");

        return self::SUCCESS;
    }
}
