<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SyncCompanionConfirmation;
use App\Models\DailyDeparture;
use App\Models\User;
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

        // Record the answer in the Protokoll (both entry points route through here),
        // parallel to how an excursion RSVP is logged — who confirmed/declined that a
        // child goes home with another, and when.
        activity()
            ->causedBy(User::find($byUserId))
            ->performedOn($departure)
            ->event($confirmed ? 'companion_yes' : 'companion_no')
            ->log($departure->child->name.' · '.$departure->companion->name);

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
