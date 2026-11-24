<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StandbyDuty;
use App\Models\User;
use App\Notifications\StandbyReminder;
use App\Support\StandbyRoster;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Reminds the families on Springerdienst for the next weekday — scheduled on weekday
 * afternoons, so Friday covers Monday. A closed next weekday has no slots and sends
 * nothing; the weekday before the next open day reminds instead.
 */
class RemindStandby extends Command
{
    protected $signature = 'standby:remind {--dry-run : List who would be reminded without sending anything}';

    protected $description = 'Remind guardians (Slack and/or push) whose child is on Springerdienst the next weekday.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $date = Carbon::today()->addWeekday();
        $slots = StandbyRoster::slotsBetween($date, $date)[$date->toDateString()] ?? 0;

        // Duties left over on a day that has since closed (or lost its second slot) aren't real.
        $childIds = StandbyDuty::query()
            ->whereDate('date', $date)
            ->where('slot', '<=', $slots)
            ->whereNotNull('child_id')
            ->whereHas('child', fn ($query) => $query->activeOn($date))
            ->pluck('child_id');

        $guardians = User::query()
            ->reachable()
            ->whereHas('children', fn ($query) => $query->whereIn('children.id', $childIds))
            ->orderBy('name')
            ->get();

        foreach ($guardians as $guardian) {
            $names = $guardian->children()
                ->whereIn('children.id', $childIds)
                ->orderBy('name')
                ->pluck('name')
                ->all();

            if ($dryRun) {
                $this->line("• {$guardian->name}: ".implode(', ', $names));
            } else {
                Notification::send([$guardian], new StandbyReminder($date, $names));
            }
        }

        $day = $date->toDateString();
        $this->info($dryRun
            ? "Dry run: would remind {$guardians->count()} guardian(s) about Springerdienst on {$day}."
            : "Reminded {$guardians->count()} guardian(s) about Springerdienst on {$day}.");

        return self::SUCCESS;
    }
}
