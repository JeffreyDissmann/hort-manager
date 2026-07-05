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
            // Anyone reachable by Slack and/or web push; via() picks the channel(s).
            $guardians = $departure->child->guardians()->reachable()->get();

            Notification::send($guardians, new ChildDeparted($departure));
        }
    }
}
