<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the interface language for every request: the one chosen with the
 * language switch (kept in the session), otherwise APP_LOCALE (English).
 */
class SetLocale
{
    public const string SESSION = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get(self::SESSION) : null;

        if (! is_string($locale) || ! in_array($locale, config('learning.locales'), true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);
        Date::setLocale($locale); // month / day names

        return $next($request);
    }
}
