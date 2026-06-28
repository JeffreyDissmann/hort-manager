<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DailyDeparture;
use App\Notifications\ChildDeparted;
use Illuminate\Support\Facades\Notification;

class DailyDepartureObserver
{
    /** When a child is marked off (left_at set), DM their Slack guardians. */
    public function updated(DailyDeparture $departure): void
    {
        if ($departure->wasChanged('left_at') && $departure->left_at !== null) {
            Notification::send(
                $departure->child->guardians()->onSlack()->get(),
                new ChildDeparted($departure),
            );
        }
    }
}
