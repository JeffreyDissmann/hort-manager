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

    /** The arrangement can be unwound before the ask goes out — then there is nothing
     *  to ask about, and a ModelNotFound would only land in failed_jobs. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public DailyDeparture $departure) {}

    public function handle(SlackCompanion $slack): void
    {
        $slack->ask($this->departure);
    }
}
