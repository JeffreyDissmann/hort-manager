<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SlackController extends Controller
{
    /**
     * "Sign in with Slack" via OpenID Connect. The provider switches to the
     * OIDC endpoints automatically when given these scopes.
     *
     * @var list<string>
     */
    private const SCOPES = ['openid', 'email', 'profile'];

    /** Send the user to Slack's "Sign in with Slack" consent screen. */
    public function redirect(): RedirectResponse
    {
        $params = array_filter(['team' => config('services.slack.team')]);

        $response = Socialite::driver('slack')
            ->setUserScopes(self::SCOPES)
            ->with($params)
            ->redirect();

        // Route through the Hort's own workspace subdomain so Slack skips its
        // "enter your workspace URL" picker and goes straight to authorize.
        if ($workspace = config('services.slack.workspace')) {
            return redirect(str_replace(
                'https://slack.com/',
                "https://{$workspace}.slack.com/",
                $response->getTargetUrl(),
            ));
        }

        return $response;
    }

    /** Handle the Slack callback: find or create the user, then sign them in. */
    public function callback(): RedirectResponse
    {
        try {
            $slackUser = Socialite::driver('slack')->setUserScopes(self::SCOPES)->user();
        } catch (\Throwable) {
            return $this->failed('Die Slack-Anmeldung ist fehlgeschlagen. Bitte versuche es erneut.');
        }

        // Restrict sign-in to the configured Hort workspace (when one is set).
        $allowedTeam = config('services.slack.team');
        if ($allowedTeam && ($slackUser->getRaw()['https://slack.com/team_id'] ?? null) !== $allowedTeam) {
            return $this->failed('Dieser Slack-Account gehört nicht zum Hort-Workspace.');
        }

        // Existing account by Slack id, or by the email Slack returns.
        $user = User::firstWhere('slack_id', $slackUser->getId())
            ?? ($slackUser->getEmail() ? User::firstWhere('email', $slackUser->getEmail()) : null);

        if ($user) {
            $user->forceFill([
                'slack_id' => $slackUser->getId(),
                'avatar' => $slackUser->getAvatar(),
            ])->save();
        } else {
            if (! $slackUser->getEmail()) {
                return $this->failed('Slack hat keine E-Mail-Adresse freigegeben. Bitte erlaube den Zugriff auf deine E-Mail.');
            }

            // New parents self-provision on first Slack sign-in (trust-based).
            $user = User::create([
                'name' => $slackUser->getName() ?: 'Elternteil',
                'email' => $slackUser->getEmail(),
                'slack_id' => $slackUser->getId(),
                'avatar' => $slackUser->getAvatar(),
                'role' => UserRole::Parent,
            ]);

            // Slack vouches for the email, so it counts as verified.
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['slack' => $message]);
    }
}
