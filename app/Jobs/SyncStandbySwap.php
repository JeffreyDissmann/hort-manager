<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StandbySwap;
use App\Services\SlackStandbySwap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Re-render every guardian's swap DM once the request is answered, withdrawn or lapsed. */
class SyncStandbySwap implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public StandbySwap $swap) {}

    public function handle(SlackStandbySwap $slack): void
    {
        $slack->sync($this->swap);
    }
}
