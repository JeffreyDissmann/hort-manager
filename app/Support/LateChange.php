<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LateChange as LateChangeNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * „Späte Änderung": when a parent still changes something about *today* after the
 * Hort-wide cutoff, staff can't rely on the morning's plan any more — so they get a
 * notification. Staff's own changes never notify (they made them), and days other
 * than today are never late, however close to the cutoff they are entered.
 */
class LateChange
{
    /** Whether a change to `$date` by `$actor` right now counts as late. */
    public static function applies(User $actor, string $date): bool
    {
        return ! $actor->isStaff()
            && Carbon::parse($date)->isToday()
            && self::cutoffPassed();
    }

    /** Whether today's cutoff time has already passed. */
    public static function cutoffPassed(): bool
    {
        return now()->format('H:i') >= Setting::lateChangeCutoff();
    }

    /** DM every reachable staff member — no-op when the change isn't a late one. */
    public static function notify(User $actor, Child $child, string $date, string $summary): void
    {
        if (! self::applies($actor, $date)) {
            return;
        }

        $recipients = User::query()
            ->reachable()
            ->where('role', UserRole::Staff)
            ->get();

        Notification::send($recipients, new LateChangeNotification($child, $actor, $summary));
    }

    /** A one-line description of a departure's plan, for the DM body. */
    public static function describePlan(DailyDeparture $departure): string
    {
        $method = $departure->planned_method;

        if ($method === DepartureMethod::WithChild) {
            $companion = $departure->companion?->name ?? Child::find($departure->companion_child_id)?->name;

            return $companion ? "geht mit {$companion} mit" : 'geht mit einem anderen Kind mit';
        }

        $time = $departure->planned_time ? substr((string) $departure->planned_time, 0, 5) : null;

        if ($time === null) {
            return $method?->label() ?? 'kein Plan';
        }

        $prefix = $method === DepartureMethod::SentHome
            ? ($departure->time_qualifier ?? TimeQualifier::At)->prefix()
            : null;

        return trim(($method?->label() ?? '').' '.trim("{$prefix} {$time}"));
    }
}
