<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SyncCompanionConfirmation;
use App\Models\DailyDeparture;
use App\Notifications\CompanionAnswered;
use Illuminate\Support\Facades\Notification;

/**
 * Records a companion family's answer to a „geht mit … mit" request and fans out
 * the side effects. Shared by both entry points — the in-app confirmation
 * (CompanionConfirmationController) and the Slack button (SlackInteractionController)
 * — so the answer behaves identically whichever way it arrives.
 */
class CompanionAnswer
{
    public static function record(DailyDeparture $departure, bool $confirmed, int $byUserId): void
    {
        $departure->update([
            'companion_confirmed' => $confirmed,
            'companion_confirmed_by' => $byUserId,
            'companion_confirmed_at' => now(),
        ]);

        // Close the loop: tell the requesting child's family the outcome (now „mit …",
        // or — on a No — that their child stays a normal pickup at the synced time).
        Notification::send(
            $departure->child->guardians()->get(),
            new CompanionAnswered($departure, $confirmed),
        );

        // Keep every companion-guardian's Slack DM in sync with the recorded answer.
        SyncCompanionConfirmation::dispatch($departure);
    }
}
