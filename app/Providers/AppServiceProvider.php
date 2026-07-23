<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Slack\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // At runtime, generate server-side links from APP_URL (so Slack messages and
        // proxied requests point at the app, not the proxy host). NOT in console:
        // Wayfinder reads this forced root during `wayfinder:generate` and would bake
        // the absolute domain into the JS bundle — we want relative, domain-agnostic URLs.
        if (! $this->app->runningInConsole()) {
            URL::forceRootUrl(config('app.url'));
        }

        // Register the "Sign in with Slack" Socialite driver.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('slack', Provider::class);
        });

        // The built-in password-reset e-mail is English; render it in German to
        // match the rest of the app. (The framework mail template's remaining
        // string is translated in lang/de.json.)
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], absolute: false));

            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Passwort zurücksetzen')
                ->greeting('Hallo!')
                ->line('Du erhältst diese E-Mail, weil für dein Konto beim Hort-Manager eine Zurücksetzung des Passworts angefordert wurde.')
                ->action('Passwort zurücksetzen', $url)
                ->line("Der Link ist {$minutes} Minuten gültig.")
                ->line('Wenn du keine Zurücksetzung angefordert hast, musst du nichts tun.')
                ->salutation("Viele Grüße\nDein Hort-Manager");
        });

        // One configured Slack Web API client (bot token, timeout, bounded retry)
        // shared by every outbound Slack service.
        Http::macro('slack', fn (): PendingRequest => Http::baseUrl('https://slack.com/api')
            ->withToken(config('services.slack.notifications.bot_user_oauth_token'))
            ->timeout(10)
            ->retry(2, 200, throw: false));
    }
}
