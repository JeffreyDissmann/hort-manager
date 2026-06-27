<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Imports/refreshes every human Slack workspace member as a user (Elternteil by
 * default). Existing accounts keep their role/admin status; only the Slack-sourced
 * fields (name, email, avatar, slack_id) are refreshed. Needs the bot token plus
 * the users:read and users:read.email scopes.
 */
class SlackUserImporter
{
    /** @return int the number of members imported/updated */
    public function run(): int
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');

        if (! $token) {
            return 0;
        }

        $count = 0;
        $cursor = null;

        do {
            $response = Http::withToken($token)
                ->get('https://slack.com/api/users.list', array_filter([
                    'limit' => 200,
                    'cursor' => $cursor,
                ]))
                ->json();

            foreach ($response['members'] ?? [] as $member) {
                if ($this->import($member)) {
                    $count++;
                }
            }

            $cursor = $response['response_metadata']['next_cursor'] ?? null;
        } while ($cursor);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $member
     */
    private function import(array $member): bool
    {
        // Skip bots, Slackbot and deactivated accounts.
        if (($member['is_bot'] ?? false) || ($member['deleted'] ?? false) || ($member['id'] ?? null) === 'USLACKBOT') {
            return false;
        }

        $email = $member['profile']['email'] ?? null;

        if (! $email) {
            return false; // no usable account without an email
        }

        // Match by Slack id, else by email; otherwise a fresh parent account.
        $user = User::firstWhere('slack_id', $member['id'])
            ?? User::firstWhere('email', $email);

        if (! $user) {
            $user = new User;
            $user->role = UserRole::Parent;
            $user->email_verified_at = now(); // Slack vouches for the email
        }

        $user->forceFill([
            'name' => $member['profile']['real_name'] ?? $member['real_name'] ?? $member['name'] ?? 'Elternteil',
            'email' => $email,
            'slack_id' => $member['id'],
            'avatar' => $member['profile']['image_192'] ?? null,
        ])->save();

        return true;
    }
}
