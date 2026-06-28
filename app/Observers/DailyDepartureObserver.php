<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DailyDeparture;
use App\Notifications\ChildDeparted;
use Illuminate\Support\Facades\Notification;

class DailyDepartureObserver
{
    /** When a child is marked off (left_at set), notify guardians via Slack and/or push. */
    public function updated(DailyDeparture $departure): void
    {
        if ($departure->wasChanged('left_at') && $departure->left_at !== null) {
            // Anyone reachable: a Slack id and/or a web-push subscription. The
            // notification's via() picks the right channel(s) per guardian.
            $guardians = $departure->child->guardians()
                ->where(fn ($query) => $query
                    ->whereNotNull('slack_id')
                    ->orWhereHas('pushSubscriptions'))
                ->get();

            Notification::send($guardians, new ChildDeparted($departure));
        }
    }
}
