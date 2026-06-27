<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-Slack-Signature on inbound interaction requests so only Slack
 * can drive RSVP actions. See https://api.slack.com/authentication/verifying-requests-from-slack
 */
class VerifySlackSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.slack.signing_secret');
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        abort_if(! $secret || ! $timestamp || ! $signature, 403, 'Missing Slack signature.');

        // Reject stale requests (replay protection).
        abort_if(abs(time() - (int) $timestamp) > 300, 403, 'Stale Slack request.');

        $expected = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$request->getContent()}", $secret);

        abort_unless(hash_equals($expected, $signature), 403, 'Invalid Slack signature.');

        return $next($request);
    }
}
