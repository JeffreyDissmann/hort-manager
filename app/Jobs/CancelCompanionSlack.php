<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SlackCompanion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Replace the companion DMs with a „hat sich erledigt" note when the arrangement is
 * unwound. Carries plain message coordinates (channel/ts) + names, so it works even
 * though the departure row — and its remembered messages — are already gone.
 */
class CancelCompanionSlack implements ShouldQueue
{
    use Queueable;

    /** @param array<int, array{channel: string, ts: string}> $messages */
    public function __construct(
        public array $messages,
        public string $child,
        public string $companion,
    ) {}

    public function handle(SlackCompanion $slack): void
    {
        $slack->cancel($this->messages, $this->child, $this->companion);
    }
}
