<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Excursion;
use App\Models\User;
use App\Notifications\ExcursionRsvpReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class RemindExcursionRsvps extends Command
{
    protected $signature = 'excursions:remind-rsvps';

    protected $description = 'Remind guardians (Slack and/or push) who still owe an excursion answer due today.';

    public function handle(): int
    {
        Excursion::dueToday()->get()->each(function (Excursion $excursion) {
            $pendingChildren = $excursion->children()->wherePivotNull('response')->pluck('children.id');

            $guardians = User::query()
                ->reachable()
                ->whereHas('children', fn ($query) => $query->whereIn('children.id', $pendingChildren))
                ->get();

            Notification::send($guardians, new ExcursionRsvpReminder($excursion));
        });

        return self::SUCCESS;
    }
}
