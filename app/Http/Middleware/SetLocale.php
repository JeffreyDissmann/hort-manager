<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply the signed-in user's chosen UI locale. With no choice (or an unknown
 * one) the app default from config('app.locale') — German — stays in effect.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->preferredLocale();

        if ($locale !== null && array_key_exists($locale, config('locales'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
