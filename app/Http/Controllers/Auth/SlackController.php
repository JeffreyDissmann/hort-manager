<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SlackController extends Controller
{
    /** Deep-link targets the Slack messages may link to (avoids open redirects). */
    private const TARGETS = [
        'board' => 'board',
        'polls' => 'polls.index',
        'children' => 'children.index',
        'weekly-plan' => 'weekly-plan',
    ];

    /**
     * "Sign in with Slack" via OpenID Connect. The provider switches to the
     * OIDC endpoints automatically when given these scopes.
     *
     * @var list<string>
     */
    private const SCOPES = ['openid', 'email', 'profile'];

    /**
     * Entry point from a Slack link: deep-link into the app. If already signed in,
     * go straight to the target; otherwise show the normal login screen (Slack,
     * e-mail/password, or password reset) and land on the target afterwards.
     */
    public function enter(Request $request): RedirectResponse
    {
        $url = route(self::TARGETS[$request->query('to')] ?? 'dashboard');

        if (Auth::check()) {
            return redirect($url);
        }

        $request->session()->put('url.intended', $url);

        return redirect()->route('login');
    }

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
    public function callback(Request $request): RedirectResponse
    {
        try {
            $slackUser = Socialite::driver('slack')->setUserScopes(self::SCOPES)->user();
        } catch (\Throwable $e) {
            report($e); // surface misconfig / Slack API changes instead of swallowing them

            return $this->failed('Die Slack-Anmeldung ist fehlgeschlagen. Bitte versuche es erneut.');
        }

        // Only members of the configured Hort workspace may sign in. Fail closed:
        // with no workspace configured we cannot verify membership, so reject.
        $allowedTeam = config('services.slack.team');
        $signedInTeam = $slackUser->getRaw()['https://slack.com/team_id'] ?? null;

        if (! $allowedTeam || $signedInTeam !== $allowedTeam) {
            return $this->failed('Dieser Slack-Account gehört nicht zum Hort-Workspace.');
        }

        // Match an existing account by Slack id.
        $user = User::firstWhere('slack_id', $slackUser->getId());

        if (! $user) {
            if (! $slackUser->getEmail()) {
                return $this->failed('Slack hat keine E-Mail-Adresse freigegeben. Bitte erlaube den Zugriff auf deine E-Mail.');
            }

            $existing = User::firstWhere('email', $slackUser->getEmail());

            // An account already tied to a different Slack id must never be taken over.
            if ($existing && $existing->slack_id !== null) {
                return $this->failed('Diese E-Mail gehört bereits zu einem anderen Slack-Konto.');
            }

            // Adopt an as-yet-unlinked account by its Slack-verified email, else create one.
            $user = $existing ?? new User;
        }

        // forceFill (never mass assignment) so role/admin can't be set from Slack data.

        $user->forceFill([
            'slack_id' => $slackUser->getId(),
            'avatar' => $slackUser->getAvatar(),
        ]);

        if (! $user->exists) {
            // New parents self-provision on first sign-in; Slack vouches for the email.
            $user->forceFill([
                'name' => $slackUser->getName() ?: 'Elternteil',
                'email' => $slackUser->getEmail(),
                'role' => UserRole::Parent,
                'email_verified_at' => now(),
            ]);
        }

        $user->save();

        // Rotate the session id on login to prevent session fixation.
        $request->session()->regenerate();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['slack' => $message]);
    }
}
