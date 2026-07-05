<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-user cap on AI-assistant invocations, shared by the /hort command and the
 * DM entrypoint. Each reply makes up to two Ollama calls, so this stops one
 * workspace member from saturating the queue worker and the model host.
 */
class AssistantRateLimit
{
    private const MAX_PER_MINUTE = 15;

    /** Record an invocation; false when the user is over the per-minute limit. */
    public static function attempt(string $slackUserId): bool
    {
        $key = 'assistant:'.$slackUserId;

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_MINUTE)) {
            return false;
        }

        RateLimiter::hit($key);

        return true;
    }
}
