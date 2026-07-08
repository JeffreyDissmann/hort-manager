<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DailyDeparture;
use App\Services\SlackCompanion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Re-render every companion-guardian's DM after an answer, off the web request. */
class SyncCompanionConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public DailyDeparture $departure) {}

    public function handle(SlackCompanion $slack): void
    {
        $slack->sync($this->departure);
    }
}
