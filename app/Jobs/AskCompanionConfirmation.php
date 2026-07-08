<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DailyDeparture;
use App\Services\SlackCompanion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Post the interactive Ja/Nein companion DM to the companion's guardians, off-request. */
class AskCompanionConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public DailyDeparture $departure) {}

    public function handle(SlackCompanion $slack): void
    {
        $slack->ask($this->departure);
    }
}
