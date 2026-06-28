<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Http\Client\PendingRequest;
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

        // One configured Slack Web API client (bot token, timeout, bounded retry)
        // shared by every outbound Slack service.
        Http::macro('slack', fn (): PendingRequest => Http::baseUrl('https://slack.com/api')
            ->withToken(config('services.slack.notifications.bot_user_oauth_token'))
            ->timeout(10)
            ->retry(2, 200, throw: false));
    }
}
