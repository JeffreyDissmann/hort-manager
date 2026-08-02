<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SlackNotificationRouterChannel;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Wraps the Slack channel so a stale `slack_id` heals itself.
 *
 * Slack answers `channel_not_found` when the id no longer resolves — the account was
 * deactivated, the person left the workspace, or the id was never real. Without this
 * every future DM to that user fails the same way and piles into failed_jobs, unseen:
 * the departure notice, the RSVP reminders, the digest, all of them. Clearing the id
 * drops them back to web push, which still works, and re-linking happens by itself
 * the next time they „Mit Slack anmelden".
 *
 * Only that one error is swallowed; anything else (a bad token, a Slack outage) still
 * fails loudly, because those want fixing rather than papering over.
 *
 * A notification is queued per channel, so clearing the id can leave an already-queued
 * Slack job with nothing to route to — which the package answers with a LogicException.
 * That case is checked for up front rather than caught, since it isn't a failure.
 */
class SelfHealingSlackChannel
{
    /** Slack's answer when the id doesn't resolve to a conversation any more. */
    private const STALE_ID_ERROR = 'channel_not_found';

    public function __construct(private SlackNotificationRouterChannel $inner) {}

    public function send(mixed $notifiable, Notification $notification): mixed
    {
        // Nothing to send to: the id was cleared after this job was queued (see below),
        // or the user unlinked Slack meanwhile. Not an error — web push still applies.
        if (blank($notifiable->routeNotificationFor('slack', $notification))) {
            return null;
        }

        try {
            return $this->inner->send($notifiable, $notification);
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), self::STALE_ID_ERROR)) {
                throw $e;
            }

            $this->forgetSlackId($notifiable);

            return null;
        }
    }

    private function forgetSlackId(mixed $notifiable): void
    {
        if (! $notifiable instanceof User || $notifiable->slack_id === null) {
            return;
        }

        Log::warning('Cleared a Slack id Slack no longer knows.', [
            'user_id' => $notifiable->id,
            'slack_id' => $notifiable->slack_id,
        ]);

        $notifiable->forceFill(['slack_id' => null])->save();
    }
}
