<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StandbySwap;
use App\Services\SlackStandbySwap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Post the Annehmen/Ablehnen DM for a new swap request, off the web request. */
class AskStandbySwap implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public StandbySwap $swap) {}

    public function handle(SlackStandbySwap $slack): void
    {
        $slack->ask($this->swap);
    }
}
