<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Child;
use App\Models\Excursion;
use App\Services\SlackRsvp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Re-render every guardian's RSVP DM after an answer, off the web request. */
class SyncExcursionRsvp implements ShouldQueue
{
    use Queueable;

    /** Trip or child gone before the DMs are re-rendered → nothing left to sync. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Excursion $excursion, public Child $child) {}

    public function handle(SlackRsvp $slack): void
    {
        $slack->syncForChild($this->excursion, $this->child);
    }
}
